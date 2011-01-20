#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "appstore.h"
#include "update.h"
#include "downloading.h"
#include "vista.h"

arguments_t* local_arguments; /* A copy for use in the callbacks */

/**
 * Update from the given url
 *
 * If the URL is in the database already, download to the file
 * at the given location. Otherwise download to a default local
 * file location.
 */
int update_from_url(appstore_t* store, int message_level, char* url) {
    int existed;
    char* filename;
    filename = appstore_find_source_local(store, url, &existed);
    if (!filename) return 1;

    if (message_level >= 1) 
        printf(existed ? "Updating software catalog from `%s'...\n" : "Adding new catalog source `%s'...\n", url);

    return direct_download(url, filename, message_level >= 1);
}

int update_all_callback(void* store, int colc, char** colv, char** coln) {
    update_from_url((appstore_t*)store, local_arguments->message_level, colv[0]);
    return 0;
}

/**
 * Do update
 *
 * Semantics:
 *   - Arguments: add if needed, and update each argument
 *   - No arguments: update all urls
 */
int do_update(arguments_t* args, appstore_t* store) {
    int err = 0;
    int i;

    if (administrator_prompt(args)) return 1;

    if (args->targets[0]) {
        /* There are arguments, update each argument */
        for (i = 0; args->targets[i]; i++)
            err |= update_from_url(store, args->message_level, args->targets[i]);
    }
    else {
        char* err_msg;
        local_arguments = args;
        if (sqlite3_exec(store->db, "SELECT url FROM sources", &update_all_callback, store, &err_msg)) {
            fprintf(stderr, "Error updating sources: %s.\n", err_msg);
            sqlite3_free(err_msg);
            err = 1;
        }
    }

    if (!err)
        printf("Done.\n");

    return err;
}
