#include <stdio.h>
#include <stdlib.h>

#include "my_md5.h"
#include "md5.h"
#include "error.h"

int do_md5(arguments_t* args, appstore_t* store) {
    int i;

    // Take the arguments as filenames and calculate their md5sums
    for (i = 0; args->targets[i]; i++) {
        char* md5sum = calculate_md5(args->targets[i]);
        if (md5sum != NULL) printf("%s: %s\n", args->targets[i], md5sum);
    }

    return 0;
}

char hex_output[16*2 + 1];
#define BUF_SIZE 2048
md5_byte_t buffer[BUF_SIZE];
char* calculate_md5(char* filename) {
	md5_state_t state;
	md5_byte_t  digest[16];
	int di;
    FILE* file;

    if (( file = fopen(filename, "rb")) == NULL) {
        fprintf(stderr, "Error reading file `%s': %s\n", filename, error_message(errno | LIBC_OP));
        return NULL;
    }

	md5_init(&state);
    while (!feof(file)) {
        int r = fread(buffer, sizeof(md5_byte_t), BUF_SIZE, file);
        md5_append(&state, (const md5_byte_t*)buffer, r);
    }
	md5_finish(&state, digest);

	for (di = 0; di < 16; ++di) sprintf(hex_output + di * 2, "%02x", digest[di]);

    fclose(file);

    return hex_output;
}
