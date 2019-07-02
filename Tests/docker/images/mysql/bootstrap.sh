#!/bin/sh

echo "[`date`] Bootstrapping MySQL..."

clean_up() {
    # Perform program exit housekeeping
    echo "[`date`] Stopping the service..."
    pkill --signal term mysqld
    exit
}

# Allow any process to see if bootstrap finished by looking up this file
if [ -f /var/run/bootstrap_ok ]; then
    rm /var/run/bootstrap_ok
fi

# Fix UID & GID for user 'mysql'

echo "[`date`] Fixing filesystem permissions..."

ORIGPASSWD=$(cat /etc/passwd | grep mysql)
ORIG_UID=$(echo $ORIGPASSWD | cut -f3 -d:)
ORIG_GID=$(echo $ORIGPASSWD | cut -f4 -d:)
ORIG_HOME=$(echo "$ORIGPASSWD" | cut -f6 -d:)
DEV_UID=${DEV_UID:=$ORIG_UID}
DEV_GID=${DEV_GID:=$ORIG_GID}

if [ "$DEV_UID" != "$ORIG_UID" -o "$DEV_GID" != "$ORIG_GID" ]; then

    # note: we allow non-unique user and group ids...
    groupmod -o -g "$DEV_GID" mysql
    usermod -o -u "$DEV_UID" -g "$DEV_GID" mysql

    # does mysql user have a root dir created by default ?
    #chown "${DEV_UID}":"${DEV_GID}" "${ORIG_HOME}"
    #chown -R "${DEV_UID}":"${DEV_GID}" "${ORIG_HOME}"/.*

fi

chown -R mysql:mysql /var/run/mysqld

if [ -d /tmpfs ]; then
    chmod 0777 /tmpfs
fi

echo "[`date`] Handing over control to /entrypoint.sh..."

trap clean_up TERM

/entrypoint.sh $@ &

echo "[`date`] Bootstrap finished" | tee /var/run/bootstrap_ok

tail -f /dev/null &
child=$!
wait "$child"
