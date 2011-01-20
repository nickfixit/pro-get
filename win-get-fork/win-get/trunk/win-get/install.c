#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <windows.h>

#include "install.h"
#include "downloading.h"
#include "error.h"
#include "zip.h"
#include "files.h"
#include "vista.h"

int to_cwd;
int found;
int worked;

arguments_t* arguments;
int lf_index = 0;
char** local_files = NULL;

#define ALLOC_STRINGLIST(cnt) (char**)calloc(cnt + 1, sizeof(char*))
#define FREE_STRINGLIST(list) { \
  int i; \
  for (i = 0; list[i]; i++) if (list[i]) free(list[i]); \
  free(list); \
}

void check_exists(sqlite3_stmt* stmt) {
    found = 1;
}

int do_download(sqlite3_stmt* stmt) {
    int   err;
    char* local_file;

    if (arguments->message_level >= 2) printf("Trying mirror `%s'...\n", sqlite3_column_text(stmt, 0));

    err = download_file(
        (char*)sqlite3_column_text(stmt, 0),  /* url */
        (char*)sqlite3_column_text(stmt, 1),  /* filename */
        (char*)sqlite3_column_text(stmt, 2),  /* md5sum */
        arguments->save_in_cwd,
        arguments->message_level >= 1, /* show progress */
        &local_file);

    if (!err) {
        local_files[lf_index] = local_file;
        worked = 1;
    }
    else
        free(local_file);

    return err;
}

int run_installer(char* executable, char* silent_args) {
    char* command_line = NULL;
    DWORD err = 0;
    STARTUPINFO si;
    PROCESS_INFORMATION pi;

    DYN_PRINTF(command_line, "%s %s", executable, silent_args ? silent_args : "");

    memset(&si, 0, sizeof(si));
    memset(&pi, 0, sizeof(pi));

    si.cb = sizeof(si);
    if (!CreateProcess(
        NULL, 
        command_line,
        NULL, /* Process attributes */
        NULL, /* Security attributes */
        0, /* Inherit handles */
        0, /* Creation flags */
        NULL, /* Environment */
        NULL, /* Working directory */
        &si,
        &pi
        )) {
        fprintf(stderr, "Error running `%s': %s.\n", command_line, error_message(GetLastError() | WIN_OP));
        err = 1;

        goto leave;
    }

    // Wait for process to exit
    if (WaitForSingleObject(pi.hProcess, INFINITE) == WAIT_FAILED) {
        fprintf(stderr, "Error waiting for process: %s.\n", error_message(GetLastError() | WIN_OP));
        err = 1;
        goto leave;
    }

    if (!GetExitCodeProcess(pi.hProcess, &err)) {
        fprintf(stderr, "Error getting process exit code: %s.\n", error_message(GetLastError() | WIN_OP));
        err = 1;
        goto leave;
    }

leave:
    free(command_line);

    return err;
}

int do_install(arguments_t* args, appstore_t* store) {
    int i, err       = 0;
    int target_count = 0;
    int succeeded    = 0;
    int auto_select  = 0;
    char** packages  = NULL;

    // Find the number of targets (for mem allocation)
    for (i = 0; args->targets[i]; i++) target_count++;

    // Next order of business: download all
    // Substeps: 
    // 1. Select package for each
    // 2. Select mirror for each
    packages = ALLOC_STRINGLIST(target_count);
    for (i = 0; args->targets[i]; i++) {
        char* package; /* Don't need to free this memory */
        if (( err = appstore_select_package(store, args->targets[i], &package) )) goto leave;

        if (strcmp(args->targets[i], package)) auto_select = 1;

        DYN_PRINTF(packages[i], "%s", package); /* A bit elaborate, but I have the macro already :) */
    }

    if (administrator_prompt(args)) return 1;

    printf("The following package%s will be %s: ", target_count > 1 ? "s" : "", args->run_installer ? "installed" : "downloaded");
    for (i = 0; packages[i]; i++) {
        if (i > 0) printf(", ");
        printf("%s", packages[i]);
    }
    printf(".\n");
    if (auto_select && !yes_no_question("Continue?", 1, args)) return 1;

    arguments = args;
    local_files = ALLOC_STRINGLIST(target_count);
    for (i = 0; packages[i]; i++) {
        lf_index = i; // Put the local-file here

        worked = 0;
        if (( err = appstore_find_mirrors(store, packages[i], "url, filename, md5sum", &do_download) )) goto leave;

        // All mirrors have been tried 
        if (!worked) {
            fprintf(stderr, "No suitable mirrors found for package `%s'.\n", packages[i]);
            err = 1;
            goto leave;
        }
    }

    if (args->run_installer) {
        sqlite3_stmt* stmt;
        char* pid_list; 
        char* where = NULL;
        char* order = NULL;

        pid_list = wrap_n_join(packages, "'%s'", ", ");
        DYN_PRINTF(where, "pid in (%s)", pid_list);
        DYN_PRINTF(order, "if(s_args(filename, silent) != '' AND %d, 0, 1) ASC, pid ASC", args->silent);
        
        // Now execute all the installers
        // First do the silent ones, because you've already been waiting for the downloads
        if (( err = appstore_find_packs(store, where, order, "pid, s_args(filename, silent), installer_in_zip", &stmt) )) goto leave_run;
        while (!appstore_find_packs_row(&stmt)) {
            const char* package    = sqlite3_column_text(stmt, 0);
            char* silent_arg       = args->silent ? (char*)sqlite3_column_text(stmt, 1) : "";
            const char* zip_inst   = sqlite3_column_text(stmt, 2);
            int silent             = silent_arg && strlen(silent_arg) > 0;
            char run_err           = 0;

            if (args->message_level >= 1)
                printf("Installing `%s'%s...\n", package, silent ? " silently" : "");

            // Find package, local_file has the same index
            for (i = 0; packages[i]; i++)
                if (!strcmp(packages[i], package))
                    break;

            if (is_msi(local_files[i])) {
                /* MSI file, run as such */
                char* all_silent_args = NULL;
                DYN_PRINTF(all_silent_args, "/i \"%s\" %s", local_files[i], silent_arg);
                run_err = run_installer("msiexec.exe", all_silent_args);
                free(all_silent_args);
            }
            else if (zip_inst && strlen(zip_inst)) {
                /* Installer is contained in zip file */
                char extract_path[MAX_PATH];
                char extract_inst[MAX_PATH];

                if (args->message_level >= 2)
                    printf("Extracting archive...\n");

                if (( err = extract_to_temp(local_files[i], extract_path) )) goto leave_run;
                strcpy(extract_inst, extract_path);
                strcat(extract_inst, "\\");
                strncat(extract_inst, zip_inst, MAX_PATH - 1);

                run_err = run_installer(extract_inst, (char*)silent_arg);

                rmtree(extract_path); /* Always remove the extracted tree */
            }
            else if (is_zip(local_files[i])) {
                char extract_path[MAX_PATH];

                /* It's a zipfile that we're not supposed to run the installer from */
                /* So extract to desktop */
                if (args->message_level >= 2)
                    printf("Extracting archive...\n");

                if (( err = extract_to_dir(local_files[i], get_special_dir(WIN_USER_DESKTOP, NULL), extract_path, 0) )) goto leave_run;

                if (args->message_level >= 1)
                    printf("Package %s extracted to desktop.\n", package);
            }
            else {
                /* Installer can be run directly */
                run_err = run_installer(local_files[i], (char*)silent_arg);
            }

            if (run_err == 0)  {
                succeeded++;
                unlink(local_files[i]); /* Remove the downloaded file on success */
            }
            else
                fprintf(stderr, "Install of `%s' failed.\n", package);
        }

        if (succeeded == target_count) printf("Finished.");
        else if (succeeded == 0 && target_count == 1) { printf("Installation failed."); err = 1001; }
        else if (succeeded == 0) { printf("All installations failed."); err = 1001; }
        else { printf("Some installations failed."); err = 1000; }

leave_run:
        free(pid_list);
        free(where);
        free(order);
    }

leave:
    // Free packages memory
    if (local_files) FREE_STRINGLIST(local_files);
    if (packages) FREE_STRINGLIST(packages);

    return err;
}
