#ifndef ERROR_H
#define ERROR_H

#include <errno.h>
#include <string.h>
#include <windows.h>

#define SQLITE_OP 0x10000
#define CURL_OP   0x20000
#define LIBC_OP   0x40000
#define WIN_OP    0x80000

#define ERRDO( expr, type ) do { int r = expr; if (r != 0) return r | type; } while (0)

char* error_message(DWORD code);

#endif
