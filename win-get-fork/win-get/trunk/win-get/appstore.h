/**
 * App store file tokenizer and reader
 */

#ifndef APPSTORE_H
#define APPSTORE_H

#include <time.h>
#include <sqlite3.h>

typedef struct {
    sqlite3* db;
    time_t max_time;
} appstore_t;

int appstore_init(appstore_t* appstore);
int appstore_load_all(appstore_t* appstore, char* cmd);
int appstore_load(appstore_t* appstore, char* filename);
void appstore_cleanup(appstore_t* appstore);
int appstore_find_app(appstore_t* appstore, char* name, char* columns, void (*callback)(sqlite3_stmt* stmt));
int appstore_select_package(appstore_t* store, char* descriptor, char** out_package);

int appstore_package_exists(appstore_t* store, char* package);
int appstore_app_exists(appstore_t* store, char* app);

int appstore_find_packs(appstore_t* store, char* where, char* order_by, char* columns, sqlite3_stmt** stmt);
int appstore_find_packs_row(sqlite3_stmt** stmt);

int appstore_empty(appstore_t* appstore);
int appstore_out_of_date(appstore_t* appstore);

/**
 * Loop through all mirrors of the given package until the callback returns 0
 */
int appstore_find_mirrors(appstore_t* store, char* pid, char* columns, int (*callback)(sqlite3_stmt* stmt));
char* appstore_find_source_local(appstore_t* store, char* url, int* existed);

#endif
