#include <stdlib.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <dirent.h>
#include <unistd.h>
#include <fcntl.h>
#include <windows.h>
#include <shlobj.h>
#include <libgen.h>

#include "error.h"
#include "files.h"
#include "common.h"

int read_file(char* filename, char** p_buffer) {
    FILE* infile;
    size_t numbytes;
    size_t rd, ofs;
     
    if ((infile = fopen(filename, "rb")) == NULL) return errno | LIBC_OP;
     
    /* Get the number of bytes */
    fseek(infile, 0L, SEEK_END);
    numbytes = ftell(infile);
     
    /* reset the file position indicator to 
    the beginning of the file */
    fseek(infile, 0L, SEEK_SET);	
     
    /* grab sufficient memory for the 
    buffer to hold the text */
    if (!(*p_buffer = (char*)calloc(numbytes + 1, sizeof(char)))) { fclose(infile); return errno | LIBC_OP; }
     
    /* copy all the text into the buffer */
    ofs = 0;
    while (ofs < numbytes) {
        rd  = fread(*p_buffer + ofs, sizeof(char), numbytes - ofs, infile);
        ofs += rd;
    }
    fclose(infile);
     
    return 0;
}

int my_mkdir(char* dir) {
    if (mkdir(dir)) {
        if (errno == EEXIST) return 0; /* If it already existed, that's also okay */

        if (errno == ENOFILE && strlen(dir) && strcmp(dir, ".")) { /* The parent directory may not exist, create that one then try again */
            char* parent = dirname(strdup(dir));
            int err;
            if (my_mkdir(parent)) { free(parent); return 1; }
            err = my_mkdir(dir);
            free(parent);
            return err;
        }
        fprintf(stderr, "Unable to create directory `%s': %s.\n", dir, error_message(errno | LIBC_OP));
        return 1;
    }
    return 0;
}

char tmpdir[MAX_PATH];
char* temp_dir() {
    if (!GetTempPath(MAX_PATH, tmpdir)) {
        tmpdir[0] = 0;
        fprintf(stderr, "Unable to get temporary directory: %s\n", error_message(GetLastError() | WIN_OP));
    }
    return tmpdir;
}

char file_exists(char* filename) {
    FILE* f;
    if (( f = fopen(filename, "r") )) {
        fclose(f);
        return 1;
    }
    return 0;
}

int rmtree(char* path) {
    int err = 0;
    struct dirent *entry;
    struct stat st;

    DIR* d = opendir(path);
    if (!d) {
        fprintf(stderr, "Unable to open directory `%s': %s.\n", path, error_message(errno | LIBC_OP));
        return 1;
    }

    while ((entry = readdir(d)) && !err) {
        char fullpath[MAX_PATH];

        if (entry->d_name[0] == '.' && (entry->d_name[1] == 0 || (entry->d_name[1] == '.'))) continue; /* Ignore . and .. */

        strncpy(fullpath, path, MAX_PATH - 1);
        strcat(fullpath, "\\");
        strncat(fullpath, entry->d_name, MAX_PATH - 1);

        // Try to unlink
        if (( err = stat(fullpath, &st) ))  {
            fprintf(stderr, "Unable to stat file `%s': %s.\n", fullpath, error_message(errno | LIBC_OP));
            goto leave;
        }

        if (S_ISDIR(st.st_mode)) 
            err = rmtree(fullpath); /* Directory: recurse */
        else
            if (( err = unlink(fullpath) )) /* File: unlink */
                fprintf(stderr, "Unable to delete file `%s': %s.\n", fullpath, error_message(errno | LIBC_OP));
    }

leave:
    closedir(d);

    if (!err) 
        if (( err = rmdir(path) ))
            fprintf(stderr, "Unable to remove directory `%s': %s.\n", path, error_message(errno | LIBC_OP));
    
    return err;
}

char* get_special_dir(int folder, char* cmd) {
    static char path[MAX_PATH];

    if (folder == WIN_COMMON_DATA || folder == WIN_USER_DATA || folder == WIN_USER_DESKTOP) {
        int csidl = 0;

        switch (folder) {
            case WIN_COMMON_DATA:
                csidl = CSIDL_COMMON_APPDATA;
                break;
            case WIN_USER_DATA:
                csidl = CSIDL_APPDATA;
                break;
            case WIN_USER_DESKTOP:
                csidl = CSIDL_DESKTOP;
                break;
        }

        if (SHGetFolderPath(NULL, csidl, NULL, 0, path) != S_OK) {
            fprintf(stderr, "Unable to get special folder: %s.\n", error_message(GetLastError() | WIN_OP));
            return NULL;
        }

        if (folder == WIN_COMMON_DATA || folder == WIN_USER_DATA) strcat(path, "\\win-get");
        return path;
    }
    
    // Return exe dir
    strcpy(path, dirname(cmd));
    return path;
}

time_t filemtime(char* file) {
    struct stat st;
    int err = 0;

    if (( err = stat(file, &st) )) {
        fprintf(stderr, "Unable to stat file `%s': %s.\n", file, error_message(errno | LIBC_OP));
        return 0;
    }

    return st.st_mtime;
}

char* static_dirname(char* path) {
    static char bufferj[MAX_PATH];
    strncpy(bufferj, path, MAX_PATH - 1);
    return dirname(bufferj);
}

char* get_ext(char* path) {
    char* ret = path + strlen(path) - 1;
    while (*ret != '.' && ret > path) ret--;
    return ret;
}

char is_msi(char* filename) {
    return ends_with_i(filename, ".msi");
}

char is_zip(char* filename) {
    return ends_with_i(filename, ".zip");
}
