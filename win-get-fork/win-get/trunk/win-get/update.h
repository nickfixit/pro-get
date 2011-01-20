#ifndef UPDATE_H
#define UPDATE_H

#include "common.h"

int do_update(arguments_t* args, appstore_t* store);
int update_from_url(appstore_t* store, int message_level, char* url);

#endif
