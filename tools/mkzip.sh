#!/bin/bash
#
#  ------------------------------------------------------------------------
#  TicketFilter Plugin for GLPI
#  Copyright (C) 2023-2026 by Chris Gralike
#  ------------------------------------------------------------------------
#
#  This script is part of the TicketFilter package for GLPI.

OLDVERSION='1.5.0'
NEWVERSION='2.0.0'

# Figure out what the GLPIpath is.
FULLPATH=$(readlink -f "$0")
KNOWN_SUFFIX="/ticketfilter/tools/mkzip.sh"
GLPIPATH="${FULLPATH%$KNOWN_SUFFIX}"

# Verify GLPIPATH points to a directory.
if [ -d "$GLPIPATH" ]; then
	find "$GLPIPATH/ticketfilter" -type f -name "*.php" -not -path "*/vendor/*" -exec sed -i "s/$OLDVERSION/$NEWVERSION/g" {} +

	# Run automated tests and verify success before release
	echo "Running automated test suite..."
	php "$GLPIPATH/ticketfilter/tests/RunAllTests.php"
	TEST_RESULT=$?
	if [ $TEST_RESULT -ne 0 ]; then
		echo "❌ Automated test suite failed! Release package will not be created."
		exit $TEST_RESULT
	fi
	echo "✅ Automated test suite passed successfully."

	# Remove old zipfiles
	rm -f "$GLPIPATH/ticketfilter/release/ticketfilter"*.zip
	
	cd "$GLPIPATH" || exit 1
	if [ -d './ticketfilter' ]; then 
		mkdir -p ./ticketfilter/release
		zip -r ./ticketfilter/release/ticketfilter.zip ./ticketfilter -x "/ticketfilter/tools/*" "/ticketfilter/ticketfilter.xml" "/ticketfilter/.vscode/*" "/ticketfilter/.gitignore" "/ticketfilter/.github/*" "/ticketfilter/.git/*" "/ticketfilter/release/*" "/ticketfilter/composer.lock" "/ticketfilter/composer.json" "/ticketfilter/vendor/*" "/ticketfilter/tests/*" "/ticketfilter/docs/superpowers/*"
		cp ./ticketfilter/release/ticketfilter.zip ./ticketfilter/release/ticketfilter_${NEWVERSION}.zip
		echo "✅ Release packages created:"
		echo "   - $GLPIPATH/ticketfilter/release/ticketfilter.zip"
		echo "   - $GLPIPATH/ticketfilter/release/ticketfilter_${NEWVERSION}.zip"
	else
		echo "/ticketfilter not found at $(pwd)"
	fi
else
	echo "Directory $GLPIPATH doesn't exist!"
fi
