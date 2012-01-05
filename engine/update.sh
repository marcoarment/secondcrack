#!/bin/bash

if [ "$1" == "" -o "$2" == "" ] ; then
    echo ""
    echo "Usage: updater.sh SOURCE_PATH SECONDCRACK_PATH"
    echo "  where SOURCE_PATH contains /posts, /templates, ..."
    echo "  and SECONDCRACK_PATH contains /cache, /engine, ..."
    echo ""
    exit 1
fi

SOURCE_PATH="$1"
SECONDCRACK_PATH="$2"
FORCE_CHECK_EVERY_SECONDS=30
UPDATE_LOG=/tmp/secondcrack-update.log

SCRIPT_LOCK_FILE="${SECONDCRACK_PATH}/engine/secondcrack-updater.pid"
BASH_LOCK_DIR="${SECONDCRACK_PATH}/engine/secondcrack-updater.sh.lock"

if mkdir "$BASH_LOCK_DIR" ; then
    trap "rmdir '$BASH_LOCK_DIR' 2>/dev/null ; exit" INT TERM EXIT

    echo "`date` -- updating secondcrack" >> $UPDATE_LOG
    php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE"

    if [ "`which inotifywait`" != "" ] ; then
        while true ; do
            inotifywait -q -q -r -t $FORCE_CHECK_EVERY_SECONDS -e close_write -e create -e delete -e moved_from "$SOURCE_PATH"
            if [ $? -eq 0 ] ; then
                echo "`date` -- updating secondcrack, a source file changed" >> $UPDATE_LOG
            else
                echo "`date` -- updating secondcrack, $FORCE_CHECK_EVERY_SECONDS seconds elapsed" >> $UPDATE_LOG
            fi
            
            php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE"
            while [ $? -eq 2 ] ; do 
                echo "`date` -- updating secondcrack, last run performed writes" >> $UPDATE_LOG
                php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE"
            done
        done
    fi

    rmdir "$BASH_LOCK_DIR" 2>/dev/null
    trap - INT TERM EXIT
else
   echo "Already running"
fi
