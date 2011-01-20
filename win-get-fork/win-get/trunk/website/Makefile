# Makefile for doing monkey business with the website
SCP=pscp
USER=rix0rrr

.PHONY: push

push:
	if [ -d '../pushtmp' ]; then rm -rf '../pushtmp'; rmdir ../pushtmp; fi
	svn export . ../pushtmp
	cd ../pushtmp && $(SCP) -r * $(USER),win-get@web.sourceforge.net:htdocs/
