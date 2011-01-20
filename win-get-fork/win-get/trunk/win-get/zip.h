#ifndef ZIP_H
#define ZIP_H

#include <unzip.h>

#include "common.h"

/**
 * Extract zip file to a directory
 *
 * A directory will be created according to the base name of the archive, and
 * the contents of the zip file will be extracted in there. The name of the
 * created directory will be placed in out_extract_dir.
 */
int extract_to_dir(char* zip_file, char* dir, char* out_extract_dir, char verbose);

int extract_to_temp(char* zip_file, char* out_extract_dir);
int do_unzip(arguments_t* args, appstore_t* store);

#endif
