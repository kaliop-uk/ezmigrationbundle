#!/bin/sh

echo "[`date`] Bootstrapping the Test container..."

clean_up() {
    # Perform program exit housekeeping

    #echo "[`date`] Stopping the Web server"
    #service apache2 stop

    #echo "[`date`] Stopping Memcached"
    #service memcached stop

    #echo "[`date`] Stopping Solr"
    #service solr stop

    exit
}

# Allow any process to see if bootstrap finished by looking up this file
if [ -f /var/run/bootstrap_ok ]; then
    rm /var/run/bootstrap_ok
fi

# Fix UID & GID for user 'test'

echo "[`date`] Fixing filesystem permissions..."

ORIGPASSWD=$(cat /etc/passwd | grep test)
ORIG_UID=$(echo "$ORIGPASSWD" | cut -f3 -d:)
ORIG_GID=$(echo "$ORIGPASSWD" | cut -f4 -d:)
ORIG_HOME=$(echo "$ORIGPASSWD" | cut -f6 -d:)
DEV_UID=${DEV_UID:=$ORIG_UID}
DEV_GID=${DEV_GID:=$ORIG_GID}

if [ "$DEV_UID" != "$ORIG_UID" -o "$DEV_GID" != "$ORIG_GID" ]; then

    groupmod -g "$DEV_GID" test
    usermod -u "$DEV_UID" -g "$DEV_GID" test

    chown "${DEV_UID}":"${DEV_GID}" "${ORIG_HOME}"
    chown -R "${DEV_UID}":"${DEV_GID}" "${ORIG_HOME}"/.*

fi

trap clean_up TERM

#echo "[`date`] Starting Memcached..."
#service memcached start

#echo "[`date`] Starting Solr..."
#service solr start

#echo "[`date`] Starting the Web server..."
#service apache2 start

if [ ! -f /tmp/setup_ok ]; then
    echo "[`date`] Setting up eZ..."
    su test -c "cd /home/test/ezmigrationbundle && ./Tests/environment/setup.sh; echo \$? > /tmp/setup_ok"
fi

echo "[`date`] Bootstrap finished" | tee /var/run/bootstrap_ok

tail -f /dev/null &
child=$!
wait "$child"
