# Build win-get
CC=gcc
INCLUDES=-Ivendor/sqlite -Ivendor/md5 -Ivendor/zlib/contrib/minizip
CFLAGS=`curl-config --cflags` -DCURL_STATICLIB -Wall
LDFLAGS=`curl-config --static-libs` -s
OUTFILE=win-get
CFILES=main.c

UNZIPSRC=\
    vendor/zlib/contrib/minizip/unzip.c \
    vendor/zlib/contrib/minizip/ioapi.c \
    vendor/zlib/contrib/minizip/iowin32.c

SOURCES=main.c appstore.c vendor/sqlite/sqlite3.c downloading.c common.c \
    error.c show.c search.c install.c vendor/md5/md5.c my_md5.c \
    zip.c update.c files.c vista.c progbar.c \
    $(UNZIPSRC)

OBJS=$(patsubst %.c, %.o, $(SOURCES))


$(OUTFILE): $(OBJS)
	$(CC) -o $(OUTFILE) $(OBJS) $(LDFLAGS) 

%.o: %.c
	$(CC) $(CFLAGS) $(INCLUDES) -c $? -o $@

clean:
	rm $(OUTFILE).exe *.o

release: win-get.exe CHANGELOG LICENSE README
	if [ "$(VERSION)" == "" ]; then echo Pass a VERSION; exit 1; fi
	make clean
	make
	if [ -d "../win-get-$(VERSION)" ]; then rm -rf "../win-get-$(VERSION)"; fi
	if [ -d "../win-get-$(VERSION)-src" ]; then rm -rf "../win-get-$(VERSION)-src"; fi
	mkdir ../win-get-$(VERSION)
	cp win-get.exe CHANGELOG LICENSE README ../win-get-$(VERSION)
	cd .. && zip -r win-get-$(VERSION).zip win-get-$(VERSION)
	svn export . ../win-get-$(VERSION)-src
	cd .. && zip -r win-get-$(VERSION)-src.zip win-get-$(VERSION)-src

.PHONY: clean release
