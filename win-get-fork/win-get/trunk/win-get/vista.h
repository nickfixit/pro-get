#ifndef VISTA_H
#define VISTA_H

#include "common.h"

/**
 * Return whether or not we need to display a warning about needing to be
 * Administrator under Windows Vista.
 */
int need_vista_warning();

/**
 * Show the Vista administrator prompt if necessary.
 *
 * Returns 0 to continue, 1 if the user aborted.
 */
int administrator_prompt(arguments_t* args);

#endif
