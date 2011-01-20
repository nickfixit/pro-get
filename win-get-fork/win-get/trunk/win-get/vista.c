#include <stdlib.h>
#include <stdio.h>
#include <windows.h>

#include "vista.h"
#include "common.h"

/**
 * Using a bit brittle way to determine whether user is admin
 *
 * From: http://vcfaq.mvps.org/sdk/21.htm 
 */
int is_probably_admin() {
    int admin = 0;
    SC_HANDLE h = OpenSCManager(NULL, NULL, SC_MANAGER_LOCK) ;

    if (h) {
        SC_LOCK lock = LockServiceDatabase(h) ;
        if (lock) {
            UnlockServiceDatabase(lock);
            admin = 1;
        }
        else {
            switch (GetLastError()) {
                case ERROR_SERVICE_DATABASE_LOCKED:
                    // Should only get this error if we would have otherwise succeeded
                    admin = 1;
                    break;         
                case ERROR_ACCESS_DENIED:
                case ERROR_INVALID_HANDLE:
                default:
                    break;
            }
        }

        CloseServiceHandle(h);
    }

    return admin;
}

int need_vista_warning() {
    int has_uac = 1, maj_version, min_version;

    maj_version = (DWORD)(LOBYTE(LOWORD(GetVersion())));
    min_version = (DWORD)(HIBYTE(LOWORD(GetVersion())));

    has_uac = maj_version >= 6;

    return has_uac && !is_probably_admin();
}

int administrator_prompt(arguments_t* args) {
    if (!need_vista_warning()) return 0;

    return !yes_no_question("Not running as administrator. Continue anyway?", 0, args);
}
