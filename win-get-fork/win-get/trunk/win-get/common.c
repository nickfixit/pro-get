#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "common.h"
#include "error.h"

int do_version(arguments_t* args, appstore_t* store) {
    printf(
        "win-get v" WINGET_VERSION " (C) 2008 by Rico Huijbers\n"
        "\n"
        "For more information about win-get, to browse the software catalog,\n"
        "to contribute, or to obtain the source code, please visit:\n"
        "\n"
        "    http://win-get.sourceforge.net/\n"
        "\n"
        "This program is free software, released under the GNU General Public License.\n"
        );
    return 0;
}

char* wrap_n_join(char** list, char* fmt, char* separator) {
    char* ret   = calloc(1, sizeof(char)); /* 0 char */
    char* piece = NULL;
    int i, newsize;

    for (i = 0; list[i]; i++) {
        DYN_PRINTF(piece, fmt, list[i]);
        newsize = strlen(ret) + strlen(piece) + (i > 0 ? strlen(separator) : 0);
        ret = realloc(ret, newsize + 1);
        if (i > 0) strcat(ret, separator);
        strcat(ret, piece);
    }

    if (piece) free(piece);
    return ret;
}

int version_compare(char* v1, char* v2) {
    char *p1, *p2;
    char part1[50], part2[50];
    int i1, i2, l1, l2;

    while (*v1 || *v2) {
        p1 = v1;
        p2 = v2;

        // Advance p1 and p2 up until the next period
        while (*p1 && *p1 != '.') p1++;
        while (*p2 && *p2 != '.') p2++;

        l1 = MIN(p1 - v1, 50);
        l2 = MIN(p2 - v2, 50);

        memset(part1, 0, sizeof(part1));
        memset(part2, 0, sizeof(part2));

        strncpy(part1, v1, l1);
        strncpy(part2, v2, l2);

        i1 = atoi(part1);
        i2 = atoi(part2);

        if (i1 < i2) return -1;
        if (i2 < i1) return 1;

        v1 = p1;
        v2 = p2;

        // If theye were periods, skip past that to the next segment
        if (*v1 == '.') v1++;
        if (*v2 == '.') v2++;
    }

    return 0;
}

int yes_no_question(char* question, int def_answer, arguments_t* args) {
    char c;

    printf("%s [%s/%s] ", question, def_answer ? "Y" : "y", def_answer ? "n" : "N");

    if (args->always_yes) {
        printf("Y\n");
        return 1;
    }

    fflush(stdout);
    do {
        c = getchar(); /* Waits for a newline anyhow */
        if (c == 'Y') c = 'y';
        if (c == 'N') c = 'n';
    } while (c != '\n' && c != 'y' && c != 'n');

    if (c == '\n') return def_answer;
    return c == 'y';
}

int ends_with_i(char* haystack, char* needle) {
    char *h = haystack, *n = needle;
    // Go to the end of the strings
    while (*(h + 1)) h++;
    while (*(n + 1)) n++;

    while (n >= needle) {
        char hc, nc;

        if (h < haystack) return 0;

        hc = *(h--);
        nc = *(n--);
        if (hc >= 'A' && hc <= 'Z') hc -= ('A' - 'a');
        if (nc >= 'A' && nc <= 'Z') nc -= ('A' - 'a');

        if (hc != nc) return 0;
    }

    return 1;
}
