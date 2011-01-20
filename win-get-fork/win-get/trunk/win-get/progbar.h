#ifndef PROGBAR_H
#define PROGBAR_H

#define MAX_STATS 60
#define RIGHT_MARGIN 79

void progress_bar_init(char* url);
int curl_progress_bar(void *clientp, double dltotal, double dlnow, double ultotal, double ulnow);
void progress_bar_done();


#endif
