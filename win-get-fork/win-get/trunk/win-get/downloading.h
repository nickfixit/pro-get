#ifndef CURL_H
#define CURL_H

#include <curl/curl.h>

int download_file(char* url, char* target_filename, char* md5sum, int to_cwd, int show_progress, char** local_file);

/**
 * Download a file from the given URL to the given local filename
 *
 * Uses a temporary filename to prevent the local file being overwritten if the download fails.
 */
int direct_download(char* url, char* local_file, int show_progress);

#endif
