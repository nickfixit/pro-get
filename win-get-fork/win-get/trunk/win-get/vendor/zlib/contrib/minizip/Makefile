CC=gcc
CFLAGS=-O -I../..
LDFLAGS=-L/usr/local/lib -lz
UNZ_OBJS = miniunz.o unzip.o ioapi.o iowin32.o
ZIP_OBJS = minizip.o zip.o   ioapi.o iowin32.o

.c.o:
	$(CC) -c $(CFLAGS) $*.c

all: miniunz minizip

miniunz:  $(UNZ_OBJS)
	$(CC) $(CFLAGS) -o $@ $(UNZ_OBJS) $(LDFLAGS)

minizip:  $(ZIP_OBJS)
	$(CC) $(CFLAGS) -o $@ $(ZIP_OBJS) $(LDFLAGS)

test:	miniunz minizip
	./minizip test readme.txt
	./miniunz -l test.zip
	mv readme.txt readme.old
	./miniunz test.zip

clean:
	/bin/rm -f *.o *~ minizip miniunz
