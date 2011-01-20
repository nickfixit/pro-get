#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "search.h"

void show_search_res(const char* aid, const char* purpose) {
    printf("%-20s   %s\n", aid, purpose);
}

int do_actual_search(char* sql, char** search_words, appstore_t* store) {
    sqlite3_stmt* stmt;
    int err = 0, found = 0;
    int i;

    if ((err = sqlite3_prepare_v2(store->db, sql, -1, &stmt, NULL))) return err;
    for (i = 0; search_words[i]; i++)
        if ((err = sqlite3_bind_text(stmt, i + 1, search_words[i], -1, SQLITE_STATIC)))
            goto leave;

    do {
        while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;
        if (err == SQLITE_ERROR) goto leave;
        if (err == SQLITE_ROW) {
            found++;
            show_search_res(
                sqlite3_column_text(stmt, 0),
                sqlite3_column_text(stmt, 1));
        }
    } while (err != SQLITE_DONE);

    err = 0;
    if (!found) printf("No matching applications found.\n");

leave:
    sqlite3_finalize(stmt);

    return err;
}

int do_search(arguments_t* args, appstore_t* store) {
    int i, s;
    char* all_sql;
    char this_sql[2048];

    // Build a query for finding an application with all words (targets), each contained in any field
    all_sql  = (char*)malloc(100); /* Enough for the following piece */
    strcpy(all_sql, "SELECT aid, purpose FROM application WHERE 1");
    s = strlen(all_sql);

    for (i = 0; args->targets[i]; i++)  {
        snprintf(this_sql, 2048, " AND (aid LIKE '%%'||?%d||'%%' OR purpose LIKE '%%'||?%d||'%%' OR description LIKE '%%'||?%d||'%%' OR website LIKE '%%'||?%d||'%%' )", i+1, i+1, i+1, i+1);
        s += strlen(this_sql);
        all_sql = (char*)realloc(all_sql, s);
        strcat(all_sql, this_sql);
    }

    return do_actual_search(all_sql, args->targets, store);
}
