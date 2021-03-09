#!/bin/bash

ATTEMPTS=1
DISPLAY_HELP=0

for opt in "$@"; do
	case $opt in
    -t=*|--attempts=*)
      ATTEMPTS="${opt#*=}"
      ;;
    -h|--help|*)
      DISPLAY_HELP=1
      ;;
	esac
done

if [ $DISPLAY_HELP -eq 1 ]; then
	echo "This script will run the WP Acceptance Tests"
	echo "Usage: ${0##*/} [OPTIONS...]"
	echo
	echo "Optional parameters:"
	echo "-t=*, --attempts=*     Number of times the tests should be executed if it fails."
	echo "-h|--help              Display this help screen"
	exit
fi

for i in $(seq 1 $ATTEMPTS); do

  ./vendor/bin/wpacceptance run

  EXIT_CODE=$?

  if [ $EXIT_CODE -ge 1 ] && [ $i -lt $ATTEMPTS ]; then
    echo
    echo '-------------------------------'
    echo
    echo "         Retrying..."
    echo "         Attempt #$(($i + 1))"
    echo
    echo '-------------------------------'
    echo
    echo
    sleep 3
  else
    break
  fi
done

exit $EXIT_CODE
