#include <stdio.h>
#include <stdlib.h>
#include <windows.h>
#include <string.h>

#include "downloading.h"
#include "common.h"
#include "error.h"
#include "my_md5.h"
#include "files.h"
#include "progbar.h"

char inited = 0;

int handle_init() {
    int err;
    if (( err = curl_global_init(CURL_GLOBAL_WIN32) )) {
        fprintf(stderr, "Error initializing libcurl: %s\n", curl_easy_strerror(err));
        return 1;
    }
    atexit(&curl_global_cleanup);
    return 0;
}

/**
 * Download the given file
 * 
 * If a file already exists and it matches the md5 sum, don't redownload.
 */
int download_file(char* url, char* target_filename, char* md5sum, int to_cwd, int show_progress, char** local_file) {
    char* localmd5;

    /* Find the location of the target file */
    *local_file = NULL;
    DYN_PRINTF( *local_file, "%s\\%s", to_cwd ? "." : temp_dir(), target_filename );

    if (file_exists(*local_file) && md5sum) {
        localmd5 = calculate_md5(*local_file);
        if (!strcmp(md5sum, localmd5)) {
            if (show_progress) printf("Already downloaded: %s. Skipping.\n", target_filename);
            return 0;
        }
    }

    return direct_download(url, *local_file, show_progress);
}

int direct_download(char* url, char* local_file, int show_progress) {
    CURL* handle;
    FILE* out_file;
    char temp_file[MAX_PATH];
    int err = 0;

    if (handle_init()) return 1;

    strcpy(temp_file, local_file);
    strcat(temp_file, ".tmp");

    if (( out_file = fopen(temp_file, "wb")) == NULL) {
        fprintf(stderr, "Error writing temporary file `%s': %s.\n", temp_file, error_message(errno | LIBC_OP));
        return 1;
    }

    handle = curl_easy_init(); 
    curl_easy_setopt(handle, CURLOPT_URL, url);
    curl_easy_setopt(handle, CURLOPT_WRITEDATA, out_file);
    curl_easy_setopt(handle, CURLOPT_FAILONERROR, 1);     /* Fail on HTTP 404, for example. */
    curl_easy_setopt(handle, CURLOPT_FOLLOWLOCATION , 1); /* Follow redirects */
    curl_easy_setopt(handle, CURLOPT_MAXREDIRS, 10);      /* But not infinitely */

    if (show_progress) {
        progress_bar_init(url);
        curl_easy_setopt(handle, CURLOPT_NOPROGRESS, 0);
        curl_easy_setopt(handle, CURLOPT_PROGRESSFUNCTION, &curl_progress_bar);
    }

    err = curl_easy_perform(handle);
    progress_bar_done();
    
    if (err) {
        long http_code;
        if (curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code) == 0 && http_code == 404)
            fprintf(stderr, "Error downloading from `%s': file not found.\n", url);
        else
            fprintf(stderr, "Error downloading from `%s': %s.\n", url, curl_easy_strerror(err));
    }

    fclose(out_file);
    curl_easy_cleanup(handle);

    // Now put the tempfile in place of the target filename (remove the old one first)
    if (err == 0 && file_exists(local_file)) {
        if (( err = unlink(local_file) )) {
            fprintf(stderr, "Error deleting `%s': %s.\n", local_file, error_message(errno | LIBC_OP));
            return err;
        }
    }

    if (!err) {
        /* Move tempfile to target file */
        if (( err = rename(temp_file, local_file) )) {
            fprintf(stderr, "Error renaming `%s' to `%s': %s.\n", temp_file, local_file, error_message(errno | LIBC_OP));
            return err;
        }
    }
    else {
        /* Remove tempfile */
        unlink(temp_file);
    }

    return err;
}
