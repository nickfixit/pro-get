#include <ctype.h>
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <getopt.h>

#include "appstore.h"
#include "common.h"
#include "show.h"
#include "search.h"
#include "install.h"
#include "my_md5.h"
#include "zip.h"
#include "update.h"

#define CMD_NONE    0
#define CMD_INSTALL 1
#define CMD_SHOW    2
#define CMD_SEARCH  3
#define CMD_UPDATE  4
#define CMD_MD5     5
#define CMD_UNZIP   6
#define CMD_VERSION 7

int do_nothing(arguments_t* args, appstore_t* store) {
    printf("Not implemented yet!");
    return 1;
}

static command_handler_t command_map[] = {
    &do_nothing,
    &do_install,
    &do_show,
    &do_search,
    &do_update,
    &do_md5,
    &do_unzip,
    &do_version,
};

void help() {
    printf(
        "Usage:\n"
        "    win-get.exe [options] [command] [arguments]\n"
        "\n"
        "Most used commands:\n"
        "    win-get.exe [options] install APP \n"
        "    win-get.exe [options] show    APP\n"
        "    win-get.exe [options] search  SEARCH WORDS\n"
        "    win-get.exe [options] update  [URL]\n"
        "\n"
        "Commands:\n"
        "    install        Download and run the installer for the application.\n"
        "    silent         Download and run the installer silently for the application.\n"
        "    download       Just download the installer to the current directory, but\n"
        "                   don't run it.\n"
        "    show           Show the details for the given application.\n"
        "    search         Search the application list for the given words.\n"
        "    update         Update the application catalog from the given website.\n"
        "\n"
        "Options:\n"
        "    -h, --help     This screen.\n"
        "    -s, --silent   When used with install, same as silent.\n"
        "    -d, --download When used with install, same as download.\n"
        "    -v, --verbose  Show more information about what's going on.\n"
        "    -q, --quiet    Don't show progress information.\n"
        "    -y, --yes      Answer yes to all questions.\n"
        );
}

int parse_arguments(arguments_t* args, int argc, char* argv[]) {
    int h = 0;
    int c;
    struct option long_options[] = {
        {"quiet",    no_argument, &args->message_level, 0}, /* Change msg level */
        {"verbose",  no_argument, &args->message_level, 2}, /* Change msg level */
        {"silent",   no_argument, &args->silent,        1}, 
        {"yes",      no_argument, &args->always_yes,    1}, 
        {"help",     no_argument, &h,                   1},
        {"download", no_argument, &args->run_installer, 0}, /* Download -> disable run */
        {0, 0, 0, 0}};
    int option_index = 0;

    args->command       = CMD_NONE;
    args->run_installer = 1;
    args->silent        = 0;
    args->message_level = 1;
    args->save_in_cwd   = 0;
    args->always_yes    = 0;

    while (1) {

        if ((c = getopt_long (argc, argv, "qvshdy", long_options, &option_index)) == -1) break;
     
        switch (c) {
             case 0:
                /* All options set a flag, so we don't need to do anything here */
                break;

            case 'q':
                args->message_level = 0;
                break;
            case 'v':
                args->message_level = 2;
                break;
            case 's':
                args->silent = 1;
                break;
            case 'h':
                h = 1;
                break;
            case 'd':
                args->run_installer = 0;
                break;
            case 'y':
                args->always_yes = 1;
                break;
        }
    }

    if (h || optind == argc || !strcmp(argv[optind], "help")) { /* Help or no arguments */
        help(); 
        return 1;
    }

    /* Recognize the first argument as command name */
    if (!strcmp(argv[optind], "install"))  { args->command = CMD_INSTALL; }
    else if (!strcmp(argv[optind], "sinstall")) { args->command = CMD_INSTALL; args->silent = 1; }
    else if (!strcmp(argv[optind], "silent")) { args->command = CMD_INSTALL; args->silent = 1; }
    else if (!strcmp(argv[optind], "auto")) { args->command = CMD_INSTALL; args->silent = 1; }
    else if (!strcmp(argv[optind], "download")) { args->command = CMD_INSTALL; args->run_installer = 0; args->save_in_cwd = 1; }
    else if (!strcmp(argv[optind], "do")) { args->command = CMD_INSTALL; args->run_installer = 0; args->save_in_cwd = 1; } /* windows-get compatibility */
    else if (!strcmp(argv[optind], "show")) { args->command = CMD_SHOW; }
    else if (!strcmp(argv[optind], "info")) { args->command = CMD_SHOW; } /* windows-get compatibility */
    else if (!strcmp(argv[optind], "search")) { args->command = CMD_SEARCH; }
    else if (!strcmp(argv[optind], "update")) { args->command = CMD_UPDATE; }
    else if (!strcmp(argv[optind], "md5")) { args->command = CMD_MD5; }
    else if (!strcmp(argv[optind], "unzip")) { args->command = CMD_UNZIP; }
    else if (!strcmp(argv[optind], "version")) { args->command = CMD_VERSION; }
    else {
        fprintf(stderr, "Unrecognized command: `%s'. Type `win-get --help' for more information.\n", argv[optind]);
        return 1;
    }
    optind++;

    /* The remaining arguments are the targets */
    args->targets = argv + optind;

    /* Only update can be called without an argument */
    if (args->command != CMD_UPDATE && args->command != CMD_VERSION && args->targets[0] == NULL) {
        fprintf(stderr, "This command requires at least one argument. Type `win-get --help' for more information.\n");
        return 1;
    }
    
    return 0;
}

int main(int argc, char* argv[]) {
    arguments_t args;
    appstore_t  store;
    int err;

    /* Disable buffering */
    setvbuf(stdin,  NULL, _IONBF, 0);
    setvbuf(stdout, NULL, _IONBF, 0);
    setvbuf(stderr, NULL, _IONBF, 0);

    if (parse_arguments(&args, argc, argv)) return 1;

    if (args.message_level >= 2) printf("Loading software catalog...\n");
    if (appstore_init(&store)) return 2;
    appstore_load_all(&store, argv[0]);
    if (args.message_level >= 2) printf("Done.\n");

    if (appstore_empty(&store) && args.command != CMD_UPDATE) {
        // If the appstore is empty, we gotta fill it first
        if (yes_no_question("Your software catalog is empty.\nDownload a copy from the win-get website?", 1, &args)) {
            if (( err = update_from_url(&store, args.message_level, STD_CATALOG_URL) )) goto leave;
            appstore_load_all(&store, argv[0]);
        }
    }

    if (appstore_out_of_date(&store))
        if (args.message_level >= 1)
            printf("Local software catalog may be out-of-date. Type `win-get update' to refresh.\n");

    // Invoke the specified command
    err = command_map[args.command](&args, &store);

leave:
    appstore_cleanup(&store);

    return err;
}
