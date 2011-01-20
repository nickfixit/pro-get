#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <time.h>

#include "progbar.h"

char download_url[MAX_PATH];
int wrote_bar;
double downloaded;
double speed;
time_t start_time;

void progress_bar_init(char* url) {
    strncpy(download_url, url, MAX_PATH - 1);
    download_url[MAX_PATH - 1] = 0;
    wrote_bar = 0;
    time(&start_time);
}

#define DISPLAY_INTERVAL 5

char* progbar_line(double current, double total) {
    static char line_buffer[MAX_PATH];
    static char stats_buffer[MAX_STATS];
    int max_url, show_speed;
    time_t delta_time = time(NULL) - start_time;

    speed = current / delta_time / 1024;
    show_speed = delta_time > 3; /* Only show speed after it has been normalized a bit */

    memset(line_buffer, 32, RIGHT_MARGIN);
    line_buffer[RIGHT_MARGIN] = 0;

    if (total == 0 || ((delta_time / DISPLAY_INTERVAL) % 2 == 1))
        /* If total is not known, or it's time to show speed */
        if (show_speed)
            snprintf(stats_buffer, MAX_STATS - 1, " [%.0fkB, %.1fkB/s]", current / 1024, speed);
        else
            snprintf(stats_buffer, MAX_STATS - 1, " [%.0fkB]", current / 1024);
    else
        snprintf(stats_buffer, MAX_STATS - 1, " [%.0fkB, %.1f%%]", current / 1024, (current * 100) / total);

    max_url = RIGHT_MARGIN - strlen(stats_buffer);
    if (strlen(download_url) > max_url) {
        strncpy(line_buffer, download_url, max_url - 3);
        memcpy(line_buffer + max_url - 3, "...", 3);
    }
    else {
        strncpy(line_buffer, download_url, strlen(download_url)); /* Prevent terminator */
    }

    memcpy(line_buffer + max_url, stats_buffer, strlen(stats_buffer));
    downloaded = current;

    return line_buffer;
}

void progress_bar_done() {
    if (wrote_bar) {
        printf("\r%s\n", progbar_line(downloaded, 0));
    }
}

int curl_progress_bar(void *clientp, double dltotal, double dlnow, double ultotal, double ulnow) {
    printf("\r%s", progbar_line(dlnow, dltotal));
    wrote_bar = 1;

    return 0;
}


