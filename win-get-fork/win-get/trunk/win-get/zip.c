/**
 * Unzip routines
 * 
 * This code is mostly based on miniunz.c from zlib/contrib/minizip.
 */
#include <stdlib.h>
#include <stdio.h>
#include <unzip.h>
#include <libgen.h>
#include <string.h>

#include "zip.h"
#include "error.h"
#include "files.h"

#define EXTRACT_BUFFER_SIZE 4096

int extract_to_temp(char* zip_file, char* out_extract_dir) {
    return extract_to_dir(zip_file, temp_dir(), out_extract_dir, 0);
}

int extract_current_file(unzFile h_zip, char* target_fname) {
    int err = 0;
    FILE* f = NULL;
    char buffer[EXTRACT_BUFFER_SIZE];
    
    if (( err = unzOpenCurrentFile(h_zip) )) {
        fprintf(stderr, "Unable to open current file from zip archive.\n");
        return 1;
    }
    
    f = fopen(target_fname, "wb");
    if (!f) {
        fprintf(stderr, "Unable to write to file `%s': %s.\n", target_fname, error_message(errno | LIBC_OP));
        err = 1;
        goto leave;
    }

    while (!unzeof(h_zip)) {
        int r = unzReadCurrentFile(h_zip, buffer, EXTRACT_BUFFER_SIZE);
        fwrite(buffer, 1, r, f);
    }

leave:
    if (f) fclose(f);
    unzCloseCurrentFile(h_zip);

    return err;
}

int extract_to_dir(char* zip_file, char* dir, char* out_extract_dir, char verbose) {
    int err = 0;
    unzFile h_zip;
    char filename[MAX_PATH];
    char ex_fname[MAX_PATH];

    // Open zip file
    h_zip = unzOpen(zip_file);
    if (!h_zip) {
        fprintf(stderr, "Cannot open zip file `%s'.\n", zip_file);
        return 1;
    }

    // Make directory
    strcpy(out_extract_dir, dir);
    strncat(out_extract_dir, "/", MAX_PATH - 1);
    strncat(out_extract_dir, basename(zip_file), MAX_PATH - 1);
    // Stop string at extension period
    if (strrchr(out_extract_dir, '.') > strrchr(out_extract_dir, '/')) *strrchr(out_extract_dir, '.') = 0;
    if (( err = my_mkdir(out_extract_dir) )) goto leave;

    while (!err) {
        // Extract current file
        if (( err = unzGetCurrentFileInfo(h_zip, NULL, filename, MAX_PATH - 1, NULL, 0, NULL, 0) )) break;

        if (verbose) printf("%s\n", filename);

        strcpy(ex_fname, out_extract_dir);
        strncat(ex_fname, "/", MAX_PATH - 1);
        strncat(ex_fname, filename, MAX_PATH - 1);

        // Some zip archives contain the directory as a separate entry, some
        // don't and just assume you will create any directory that's needed.
        // So that's what we need to do.
        
        if (ex_fname[strlen(ex_fname) - 1] == '/') {
            // Ends in / -> just a directory
            if (( err = my_mkdir(ex_fname) )) goto leave;
        }
        else {
            // Does not end in directory -> file (but make sure the directory exists first

            if (( err = my_mkdir(static_dirname(ex_fname)) )) goto leave;
            if (( err = extract_current_file(h_zip, ex_fname) )) goto leave;
        }

        // Go to next file (or quit loop due to an error)
        if (( err = unzGoToNextFile(h_zip)) == UNZ_END_OF_LIST_OF_FILE) {
            err = 0;
            break;
        }
    }

leave:
    unzClose(h_zip);

    return err;
}

int do_unzip(arguments_t* args, appstore_t* store) {
    int err = 0;
    int i;
    char out_path[MAX_PATH];

    for (i = 0; args->targets[i]; i++)
        if (( err = extract_to_dir(args->targets[i], ".", out_path, args->message_level >= 2) )) break;

    return err;
}
