#!/bin/bash
OLDVERSION='1.2.0'
NEWVERSION='1.3.0'

# Figure out what the GLPIpath is.
FULLPATH=$(readlink -f "$0")
KNOWN_SUFFIX="/ticketfilter/tools/mkzip.sh"
GLPIPATH="${FULLPATH%$KNOWN_SUFFIX}"

# Verify GLPIPATH points to an directory.
if [ -d "$GLPIPATH" ]; then
	# Update the versions in headers and files
	sed -i 's/'$OLDVERSION'/'$NEWVERSION'/g' $GLPIPATH/ticketfilter/*.php
	sed -i 's/*  @version    '$OLDVERSION'/*  @version    '$NEWVERSION'/g' $GLPIPATH/ticketfilter/src/*.php

	# Remove old zipfiles
	if [ -f "$GLPIPATH/plugins/ticketfilter.zip" ]; then
		rm -y $GLPIPATH/plugins/ticketfilter/release/ticketfilter.zip
	fi
	
	cd $GLPIPATH;
	if [ -d './ticketfilter' ]; then 
		zip -r ./ticketfilter/release/ticketfilter.zip ./ticketfilter -x "/ticketfilter/tools/*" "/ticketfilter/ticketfilter.xml" "/ticketfilter/.vscode/*" "/ticketfilter/.gitignore" "/ticketfilter/.github/*" "/ticketfilter/.git/*" "/ticketfilter/release/*" "/ticketfilter/composer.lock" "/ticketfilter/composer.json" "/ticketfilter/vendor/*" "/ticketfilter/tests/*"
	else
		echo "/ticketfilter not found at `pwd`";
	fi
else
	echo "Directory $GLPIPATH doesnt exist!";
fi
