#ifndef COMMON_H
#define COMMON_H

#include "appstore.h"

#define WINGET_VERSION "0.1.2"
#define STD_CATALOG_URL "http://win-get.sourceforge.net/default-catalog.php"

#define DAY (24 * 3600)
#define MONTH (30 * DAY)
#define CATALOG_EXPIRATION_TIME MONTH

#define FALLBACK_LANGUAGE "en"

typedef struct {
    int    command;
    int    run_installer;
    int    silent;
    int    message_level;
    int    save_in_cwd;
    int    always_yes;
    char** targets; /* Null-terminated list of targets */
} arguments_t;

typedef int (*command_handler_t)(arguments_t* args, appstore_t* store);

#define DYN_PRINTF(dst, fmt, ...) { \
   int size = snprintf(NULL, 0, fmt, __VA_ARGS__); \
   dst = dst ? realloc(dst, size + 1) : malloc(size + 1); \
   snprintf(dst, size + 1, fmt, __VA_ARGS__); \
}

/**
 * Combine a list of strings into another string by sprintf()ing and joining
 *
 * This function allocates memory, the caller is responsible for freeing it.
 * The list must be NULL-terminated.
 */
char* wrap_n_join(char** list, char* fmt, char* separator);

/**
 * Compare version strings v1 and v2
 * 
 * Return 
 *   -1 if v1 < v2
 *    0 if v1 = v2
 *    1 if v1 > v2
 */
int version_compare(char* v1, char* v2);

int yes_no_question(char* question, int def_answer, arguments_t* args);

int do_version(arguments_t* args, appstore_t* store);

int ends_with_i(char* haystack, char* needle);

#define MIN(a,b) ((a) < (b) ? (a) : (b))
#define MAX(a,b) ((a) > (b) ? (a) : (b))

#endif
