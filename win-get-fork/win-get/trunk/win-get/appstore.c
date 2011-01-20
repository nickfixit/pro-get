#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <fcntl.h>
#include <windows.h>
#include <shlobj.h>
#include <libgen.h>
#include <winnls.h>

#include "files.h"
#include "common.h"
#include "error.h"
#include "appstore.h"

// ------------------ Appstore -----------------------

#define realpath(N,R) _fullpath((R),(N),_MAX_PATH)
#define MAX_STR 2048

/**
 * IF(cond, consequent, alternative) function for SQL
 */
void sqlite_if_function(sqlite3_context* db, int argc, sqlite3_value** argv) {
    if (sqlite3_value_int(argv[0])) 
        sqlite3_result_value(db, argv[1]);
    else 
        sqlite3_result_value(db, argv[2]);
}

/**
 * S_ARGS(filename, silent_args)
 */
void sqlite_s_args_function(sqlite3_context* db, int argc, sqlite3_value** argv) {
    char* fname = (char*)sqlite3_value_text(argv[0]);

    if (is_msi(fname)) sqlite3_result_text(db, "/qb-", -1, NULL);
    else sqlite3_result_value(db, argv[1]);
}

/**
 * Return the name of the catalog file that's currently being loaded
 */
char current_catalog[MAX_PATH];
void sqlite_current_catalog_function(sqlite3_context* db, int argc, sqlite3_value** argv) {
    sqlite3_result_text(db, current_catalog, -1, NULL);
}

void sqlite_require_version_function(sqlite3_context* db, int argc, sqlite3_value** argv) {
    if (version_compare((char*)sqlite3_value_text(argv[0]), WINGET_VERSION) > 0) {
        char* msg = NULL;
        DYN_PRINTF(msg, "Catalog file requires win-get %s, running %s", sqlite3_value_text(argv[0]), WINGET_VERSION);
        sqlite3_result_error(db, msg, -1);
        free(msg);
    }
    else
        sqlite3_result_int(db, 1); /* True or something :) */
}

int sqlite_version_collation(void* user_ptr, int n1, const void* s1, int n2, const void* s2) {
    char v1[100], v2[100];
    memcpy(v1, s1, n1); v1[n1] = 0;
    memcpy(v2, s2, n2); v2[n2] = 0;

    return version_compare(v1, v2);
}

/**
 * Return the preferred user language
 *
 * We actually use the -system- locale instead of the -user- locale, since I
 * run an English Windows, but my locale settings are Dutch. I prefer my
 * applications in English as well.
 */
char* get_preferred_language() {
    static char syslang[20];
    static char region[10];

    syslang[0] = 0;
    
    GetLocaleInfo(LOCALE_SYSTEM_DEFAULT, LOCALE_SISO639LANGNAME, syslang, 20);
    GetLocaleInfo(LOCALE_SYSTEM_DEFAULT, LOCALE_SISO3166CTRYNAME, region, 10);
    strcat(syslang, "_");
    strcat(syslang, region);

    return syslang;
}

int appstore_init(appstore_t* appstore) {
    char* sqlite_error = NULL;
    int err;

    if (( err = sqlite3_open(":memory:", &appstore->db) )) {
        fprintf(stderr, "Unable to initialize app store.\n");
        return 1;
    }

    if (!err) err = sqlite3_create_function(appstore->db,  "if", 3, SQLITE_ANY, NULL, &sqlite_if_function, NULL, NULL);
    if (!err) err = sqlite3_create_function(appstore->db,  "current_catalog", 0, SQLITE_ANY, NULL, &sqlite_current_catalog_function, NULL, NULL);
    if (!err) err = sqlite3_create_function(appstore->db,  "require_version", 1, SQLITE_ANY, NULL, &sqlite_require_version_function, NULL, NULL);
    if (!err) err = sqlite3_create_function(appstore->db,  "s_args", 2, SQLITE_ANY, NULL, &sqlite_s_args_function, NULL, NULL);
    if (!err) err = sqlite3_create_collation(appstore->db, "version_number", SQLITE_UTF8, NULL, &sqlite_version_collation);

    if (!err) err = sqlite3_exec(appstore->db, 
        "CREATE TABLE application(aid UNIQUE ON CONFLICT REPLACE, purpose, description DEFAULT '', website DEFAULT '');"
        "CREATE TABLE package(pid UNIQUE ON CONFLICT REPLACE, aid, version COLLATE version_number, language DEFAULT '*', filename UNIQUE ON CONFLICT ROLLBACK, silent DEFAULT '', size DEFAULT -1, md5sum DEFAULT '', installer_in_zip DEFAULT '');"
        "CREATE TABLE mirror(pid, url);"
        "CREATE TABLE sources(url UNIQUE, filename);"
        "CREATE TRIGGER casc_del_pack DELETE ON application BEGIN DELETE FROM package WHERE package.aid = old.aid; END;"
        "CREATE TRIGGER casc_del_mirror DELETE ON package BEGIN DELETE FROM mirror WHERE mirror.pid = old.pid; END;"
        "CREATE TRIGGER set_filename AFTER INSERT ON sources BEGIN UPDATE sources SET filename = current_catalog() WHERE _ROWID_ = new._ROWID_; END;"
        ,
        NULL, NULL, &sqlite_error);

    if (err) {
        fprintf(stderr, "Unable to initialize app store: %s\n", sqlite_error ? sqlite_error : sqlite3_errmsg(appstore->db));
        if (sqlite_error) sqlite3_free(sqlite_error);
        sqlite3_close(appstore->db);
        return 1;
    }

    appstore->max_time = 0;

    return 0;
}

int appstore_load(appstore_t* appstore, char* filename) {
    char* buffer, *sqlite_error;
    int err = 0;

    /* Store filename of this catalog so current_catalog() function can return it */
    realpath(filename, current_catalog);

    if ((err = read_file(filename, &buffer))) {
        fprintf(stderr, "Error loading `%s': %s.\n", filename, error_message(err));
        return err;
    }

    if ((err = sqlite3_exec(appstore->db, buffer, NULL, NULL, &sqlite_error))) {
        fprintf(stderr, "Error loading `%s': %s.\n", filename, sqlite_error);
        sqlite3_free(sqlite_error);
    }

    free(buffer);

    /* Update the catalog time */
    if (!err) appstore->max_time = MAX(appstore->max_time, filemtime(filename));

    return err;
}

int appstore_out_of_date(appstore_t* appstore) {
    time_t tm;

    if (appstore->max_time == 0) return 0;

    return appstore->max_time + CATALOG_EXPIRATION_TIME < time(&tm); // One month
}

int appstore_load_dir(appstore_t* store, char* dir) {
    char pattern[MAX_PATH];
    WIN32_FIND_DATA ff;
    HANDLE hf;
    if (!dir) return 1;

    strcpy(pattern, dir);
    strcat(pattern, "/*.wgc");

    if ((hf = FindFirstFile(pattern, &ff)) == INVALID_HANDLE_VALUE) return 1;
    do {
        strcpy(pattern, dir);
        strcat(pattern, "/");
        strncat(pattern, ff.cFileName, MAX_PATH - 1); /* Recycle pattern buffer */

        appstore_load(store, pattern);
    } while (FindNextFile(hf, &ff));

    FindClose(hf);

    return 0;
}

/**
 * Load all .wgc files from the following directories (in order):
 * 
 * - Common App Data
 * - User App data
 * - Executable directory
 */
int appstore_load_all(appstore_t* appstore, char* cmd) {
    appstore_load_dir(appstore, get_special_dir(WIN_COMMON_DATA, cmd));
    appstore_load_dir(appstore, get_special_dir(WIN_USER_DATA, cmd));
    appstore_load_dir(appstore, get_special_dir(WIN_EXE_DIR, cmd));

    return 0;
}

int appstore_find_app(appstore_t* store, char* name, char* columns, void (*callback)(sqlite3_stmt* stmt)) {
    sqlite3_stmt* stmt;
    int err = 0;
    char* query = NULL;

    DYN_PRINTF(query, "SELECT %s FROM application WHERE aid = ?", columns);

    if ((err = sqlite3_prepare_v2(store->db, query, -1, &stmt, NULL))) return err;
    if ((err = sqlite3_bind_text(stmt, 1, name, -1, SQLITE_STATIC))) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;
    if (err == SQLITE_ERROR) goto leave;

    switch (err) {
        case SQLITE_DONE:
            fprintf(stderr, "Couldn't find application `%s'.\n", name);
            err = 0;
            break;

        case SQLITE_ROW:
            callback(stmt);
            err = 0;
            break;

        default:
            printf("Unexpected sqlite result code: %d.\n", err);
            break;
    }

leave:
    sqlite3_finalize(stmt);
    free(query);

    return err;
}

int appstore_find_packs(appstore_t* store, char* where, char* order_by, char* columns, sqlite3_stmt** stmt) {
    int err = 0;
    char* query = NULL;

    DYN_PRINTF(query, "SELECT %s FROM package WHERE %s ORDER BY %s", columns, where, order_by);

    err = sqlite3_prepare_v2(store->db, query, -1, stmt, NULL);

    if (err) 
        fprintf(stderr, "Error looking up packages: %s.\n", sqlite3_errmsg(store->db));

    free(query);
    return err;
}

int appstore_find_packs_row(sqlite3_stmt** stmt) {
    int err = 0;

    while ((err = sqlite3_step(*stmt)) == SQLITE_BUSY) /* retry */;
    if (err == SQLITE_ROW) return 0;

    sqlite3_finalize(*stmt);

    return err;
}

int appstore_app_exists(appstore_t* store, char* app) {
    sqlite3_stmt* stmt;
    int err = 0;
    int ret = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT aid FROM application WHERE aid = ?", -1, &stmt, NULL) )) return 0;
    if (( err = sqlite3_bind_text(stmt, 1, app, -1, SQLITE_STATIC) )) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW) 
        ret = 1;
    else
        ret = 0;
leave:
    sqlite3_finalize(stmt);
    return ret;
}

int appstore_version_exists(appstore_t* store, char* app, char* version) {
    sqlite3_stmt* stmt;
    int err = 0;
    int ret = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT pid FROM package WHERE aid = ? AND version = ?", -1, &stmt, NULL) )) return 0;
    if (( err = sqlite3_bind_text(stmt, 1, app, -1, SQLITE_STATIC) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 2, version, -1, SQLITE_STATIC) )) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW) 
        ret = 1;
    else
        ret = 0;

leave:
    sqlite3_finalize(stmt);
    return ret;
}

int appstore_langversion_exists(appstore_t* store, char* app, char* version, char* language) {
    sqlite3_stmt* stmt;
    int err = 0;
    int ret = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT pid FROM package WHERE aid = ? AND version = ? AND language LIKE ?", -1, &stmt, NULL) )) return 0;
    if (( err = sqlite3_bind_text(stmt, 1, app, -1, SQLITE_STATIC) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 2, version, -1, SQLITE_STATIC) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 3, language, -1, SQLITE_STATIC) )) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW) 
        ret = 1;
    else
        ret = 0;

leave:
    sqlite3_finalize(stmt);
    return ret;
}
int appstore_package_exists(appstore_t* store, char* package) {
    sqlite3_stmt* stmt;
    int err = 0;
    int ret = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT pid FROM package WHERE pid = ?", -1, &stmt, NULL) )) return 0;
    if (( err = sqlite3_bind_text(stmt, 1, package, -1, SQLITE_STATIC) )) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW) 
        ret = 1;
    else
        ret = 0;

leave:
    sqlite3_finalize(stmt);
    return ret;
}

/**
 * Find the largest matching application name
 *
 * Tries parts of the descriptor until one matches a known application. Example:
 * - my-app-0.3-en
 * - my-app-0.3
 * - my-app
 * - my
 *
 * Puts the discovered application name in appname.
 */
int appstore_find_application(appstore_t* store, const char* descriptor, char* appname, int n) {
    char work[MAX_STR];
    char* z;

    strncpy(work, descriptor, MAX_STR - 1);
    z = work;
    while (*(z++));

    while (work[0] && !appstore_app_exists(store, work)) {
        // Strip up to and including the next dash (seen from the end)
        while (z > work) {
            if (*(--z) == '-') {
                *z = 0;
                break;
            }
            *z = 0;
        }
    }

    if (work[0]) { /* We got something left */
        strncpy(appname, work, n);
        return 0;
    }

    return 1;
}

/**
 * Find the best matching language for the given appversion.
 */
int appstore_find_language(appstore_t* store, char* appname, char* version, char* out_language, int n) {
#define TRY_LANG(lang) if (!out_language[0]) if (appstore_langversion_exists(store, appname, version, lang)) strncpy(out_language, lang, n);
    static char sys_lang[20];
    char* sep;
    out_language[0] = 0;

    strcpy(sys_lang, get_preferred_language());

    TRY_LANG(sys_lang) // Try system language+region

    // Replace "nl_NL" with "nl%"
    sep = sys_lang;
    while (*sep != '_') sep++;
    sep[0] = '%';
    sep[1] = 0;

    TRY_LANG(sys_lang) // Try system language

    // Try fallback stuff
    TRY_LANG("*")
    TRY_LANG("")
    TRY_LANG(FALLBACK_LANGUAGE "%")

    return 0;
}

void appstore_get_highest_version(appstore_t* store, const char* appname, char* output, int n) {
    sqlite3_stmt* stmt;
    int err = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT MAX(version) FROM package WHERE aid = ?", -1, &stmt, NULL) )) return;
    if (( err = sqlite3_bind_text(stmt, 1, appname, -1, SQLITE_STATIC) )) goto leave;
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW)
        if (sqlite3_column_text(stmt, 0))
            strncpy(output, sqlite3_column_text(stmt, 0), n);

leave:
    sqlite3_finalize(stmt);
}

/**
 * Find the version number given in the meta string
 *
 * A version string must start with a number, and runs up until the next hyphen
 * or the end of the string.
 *
 * Returns a pointer to the first character after the version string (not
 * including the ending hyphen, if it is present).
 * 
 * If the meta string does not start with a version string, the highest
 * available version for the given application is looked up in the appstore,
 * and that version is used.
 */
char* appstore_find_version(appstore_t* store, const char* appname, const char* meta, char* version, int n) {
    int i = 0;
    version[0] = 0;
    memset(version, 0, n);

    if ((meta[i] >= '0' && meta[i] <= '9') || meta[i] == '.') {
        while (i < n && meta[i] != '-') {
            version[i] = meta[i];
            i++;
        }
    }

    if (!version[0]) {
        // No version found, get the greatest from the appstore
        appstore_get_highest_version(store, appname, version, n);
    }

    if (meta[i] == '-') return (char*)meta + i + 1;
    return (char*)meta + i;
}

/**
 * Find a package according to the given descriptor
 * 
 * The descriptor can be:
 * - A fully qualified package name                     (i.e. my-tool-0.2-en)
 * - An application name                                (i.e. my-tool)
 * - An application name with a version number          (i.e. my-tool-0.2)
 * - An application name with a language                (i.e. my-tool-en)
 *
 * If no version number is specified, the highest available version number is selected.
 * If no language is specified, the first available language is selected in the
 * following order:
 *
 * - (syslang + region)    'nl_NL'
 * - (just syslang)        'nl%'
 * - (multiple languages)  '*' or ''
 * - (fallback lang)       'en%'
 *
 * Lang + region is specified with an underscore instead of the standard dash,
 * to reduce conflicts with using the dash as a standard descriptor separator.
 */
int appstore_select_package(appstore_t* store, char* descriptor, char** out_package) {
    sqlite3_stmt* stmt;
    int err = 0;
    static char pack_buffer[MAX_STR];
    static char appname[MAX_STR];
    static char version[MAX_STR];
    static char language[MAX_STR];
    char* lang;
    char* next;

    // If this is a literal package name, we're done
    if (appstore_package_exists(store, descriptor)) {
        strncpy(pack_buffer, descriptor, MAX_STR - 1);
        *out_package = pack_buffer;
        return 0;
    }

    // Otherwise, we need to search for what the user meant
    //
    // First of all, determine what kind of application we are looking for
    if (( err = appstore_find_application(store, descriptor, appname, MAX_STR) )) {
        fprintf(stderr, "No such package or application `%s'.\n", descriptor);
        return err;
    }

    // Now that we've found the application name, everything else is meta
    next = descriptor + MIN( strlen(appname) + 1, strlen(descriptor) );
    next = appstore_find_version(store, appname, next, version, MAX_STR); /* Extract or find version number */
    if (!strlen(version)) {
        fprintf(stderr, "No releases available for `%s'.\n", appname);
        return 1;
    }

    if (!appstore_version_exists(store, appname, version)) {
        fprintf(stderr, "`%s' not available in the requested version, `%s'.\n", appname, version);
        return 1;
    }

    lang = next; /* What remains must be the language */
    if (strlen(lang)) {
        if (!appstore_langversion_exists(store, appname, version, lang)) {
            fprintf(stderr, "`%s-%s' not available in requested language `%s'. Select a different package.\n", appname, version, lang);
            return 1;
        }
    }
    else {
        appstore_find_language(store, appname, version, language, MAX_STR);
        if (!strlen(language)) {
            fprintf(stderr, "`%s-%s' is not available in your system language.\n", appname, version);
            return 1;
        }
        lang = language;
    }

    if (( err = sqlite3_prepare_v2(store->db, "SELECT pid FROM package WHERE aid = ? AND version = ? AND language LIKE ?", -1, &stmt, NULL) )) return err;
    if (( err = sqlite3_bind_text(stmt, 1, appname, -1, SQLITE_STATIC) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 2, version, -1, SQLITE_STATIC) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 3, lang, -1, SQLITE_STATIC) )) goto leave;

    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;
    if (err == SQLITE_ERROR) goto leave;

    switch (err) {
        case SQLITE_DONE:
            fprintf(stderr, "No such package or application `%s'.\n", descriptor);
            err = 1;
            break;

        case SQLITE_ROW:
            if (out_package) {
                strncpy(pack_buffer, sqlite3_column_text(stmt, 0), MAX_STR);
                *out_package = pack_buffer;
            }
            err = 0;
            break;

        default:
            printf("Unexpected sqlite result code: %d.\n", err);
            break;
    }

leave:
    sqlite3_finalize(stmt);

    return err;
}

int appstore_find_mirrors(appstore_t* store, char* pid, char* columns, int (*callback)(sqlite3_stmt* stmt)) {
    sqlite3_stmt* stmt;
    int err = 0;
    char* query = NULL;


    DYN_PRINTF(query, "SELECT %s FROM mirror JOIN package USING (pid) WHERE pid = ?", columns);

    if (( err = sqlite3_prepare_v2(store->db, query, -1, &stmt, NULL) )) return err;
    if (( err = sqlite3_bind_text(stmt, 1, pid, -1, SQLITE_STATIC) )) goto leave;

    do {
        while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

        if (err == SQLITE_ERROR) goto leave;
        if (err == SQLITE_ROW) {
            if (!callback(stmt)) {
                // Succes, so stop looking
                err = 0;
                goto leave;
            }
        }

    } while (err != SQLITE_DONE);

leave:
    sqlite3_finalize(stmt);
    free(query);

    return err;
}

/**
 * Make a new local filename name for the download file originating from this URL.
 */
char* make_download_path(char* buffer, char* url) {
    char* fn;

    my_mkdir(get_special_dir(WIN_USER_DATA, "")); /* Make sure user data directory exists */

    strcpy(buffer, get_special_dir(WIN_USER_DATA, ""));
    strcat(buffer, "/");
    fn = buffer + strlen(buffer);
    strncat(buffer, url, MAX_PATH - 1);

    while (*fn) {
        if (!('a' <= *fn && *fn <= 'z') && !('A' <= *fn && *fn <= 'Z') &&
            !('0' <= *fn && *fn <= '9')) *fn = '_';
        fn++;
    }

    return buffer;
}

char fnamebuffer[MAX_PATH];
char* appstore_find_source_local(appstore_t* store, char* url, int* existed) {
    sqlite3_stmt* stmt;
    int err = 0;

    if (( err = sqlite3_prepare_v2(store->db, "SELECT filename FROM sources WHERE url = ?", -1, &stmt, NULL) )) goto leave;
    if (( err = sqlite3_bind_text(stmt, 1, url, -1, SQLITE_STATIC) )) goto leave;

    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW) { /* Found */
        strcpy(fnamebuffer, sqlite3_column_text(stmt, 0));
        err = 0;
        *existed = 1;
        goto leave;
    }

    if (err == SQLITE_DONE) { /* Not found */
        make_download_path(fnamebuffer, url);
        strcat(fnamebuffer, ".wgc");
        err = 0;
        *existed = 0;
        goto leave;
    }

leave:
    if (err)
        fprintf(stderr, "Unable to find local file for `%s': %s.\n", url, sqlite3_errmsg(store->db));

    sqlite3_finalize(stmt);
    return err ? NULL : fnamebuffer;
}

void appstore_cleanup(appstore_t* appstore) {
    sqlite3_close(appstore->db);
}

int appstore_empty(appstore_t* appstore) {
    sqlite3_stmt* stmt;
    int err = 0;
    int ret = 0;

    if (( err = sqlite3_prepare_v2(appstore->db, "SELECT COUNT(*) FROM package", -1, &stmt, NULL) )) {
        fprintf(stderr, "Error querying software catalog: %s.\n", sqlite3_errmsg(appstore->db));
        return 0;
    }
    while ((err = sqlite3_step(stmt)) == SQLITE_BUSY) /* retry */;

    if (err == SQLITE_ROW)
        ret = sqlite3_column_int(stmt, 0) == 0;
    else {
        fprintf(stderr, "Error querying software catalog: %s.\n", sqlite3_errmsg(appstore->db));
        ret = 0;
    }

    sqlite3_finalize(stmt);
    return ret;
}
