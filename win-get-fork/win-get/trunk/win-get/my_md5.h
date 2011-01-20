#ifndef MY_MD5_H
#define MY_MD5_H

#include "common.h"
#include "appstore.h"

int do_md5(arguments_t* args, appstore_t* store);
char* calculate_md5(char* filename);

#endif
