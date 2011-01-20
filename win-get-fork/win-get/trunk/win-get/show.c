#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "show.h"

#define NONEMPTY(format_string, text) if (strlen(text)) printf(format_string, text)

void show_one(sqlite3_stmt* stmt) {
    NONEMPTY("Application: %s\n", sqlite3_column_text(stmt, 0));
    NONEMPTY("Purpose: %s\n",     sqlite3_column_text(stmt, 1));
    NONEMPTY("Description: %s\n", sqlite3_column_text(stmt, 2));
    NONEMPTY("Website: %s\n",     sqlite3_column_text(stmt, 3));
    printf("\n");
}

int do_show(arguments_t* args, appstore_t* store) {
    int i, err;

    // Try to find all mentioned applications and show the info
    for (i = 0; args->targets[i]; i++) 
        if ((err = appstore_find_app(store, args->targets[i], "aid, purpose, description, website", &show_one))) {
            fprintf(stderr, "Unable to show application `%s': %s\n", args->targets[i], sqlite3_errmsg(store->db));
            return 1;
        }
    
    return 0;
}
