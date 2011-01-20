#include <stdio.h>

#include "error.h"

char error_buffer[512];
char* get_win_error(DWORD code) {
    int l;

    strcpy(error_buffer, "Error message too large for buffer.");

    if (!FormatMessage(
        FORMAT_MESSAGE_IGNORE_INSERTS | FORMAT_MESSAGE_FROM_SYSTEM,
        NULL,
        code,
        0,
        error_buffer,
        512,
        NULL)) printf("Formatting error: %d\n", GetLastError());

    /* Strip period and newline from the right (prepare for printing) */
    l = strlen(error_buffer);
    while (error_buffer[l - 1] == '.' || error_buffer[l - 1] == '\n' || error_buffer[l - 1] == '\r') error_buffer[--l] = 0;

    return error_buffer;
}

char* error_message(DWORD code) {
    if (code & LIBC_OP) return strerror(code & ~LIBC_OP);
    if (code & SQLITE_OP) return "You should call sqlite3_errmsg() directly.";
    if (code & WIN_OP) return get_win_error(code & ~WIN_OP);

    return "Undefined kinda error thingy";
}
