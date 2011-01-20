#ifndef FILES_H
#define FILES_H


int read_file(char* filename, char** p_buffer);

char* temp_dir();
char file_exists(char* filename);
/**
 * Recursively delete a directory tree
 */
int rmtree(char* dir);

/** Create a directory if it doesn't already exist */
int my_mkdir(char* dir);

#define WIN_COMMON_DATA 1
#define WIN_USER_DATA 2
#define WIN_EXE_DIR 3
#define WIN_USER_DESKTOP 4

char* get_special_dir(int folder, char* cmd);
char* static_dirname(char* path);
char* get_ext(char* path);

time_t filemtime(char* file);

char is_msi(char* filename);
char is_zip(char* filename);

#endif
