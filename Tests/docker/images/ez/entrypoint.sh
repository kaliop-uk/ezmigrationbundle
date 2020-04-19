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

    if [ -f /var/run/bootstrap_ok ]; then
        rm /var/run/bootstrap_ok
    fi
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
CONTAINER_USERUID=${CONTAINER_USERUID:=$ORIG_UID}
CONTAINER_USERGID=${CONTAINER_USERGID:=$ORIG_GID}

if [ "$CONTAINER_USERUID" != "$ORIG_UID" -o "$CONTAINER_USERGID" != "$ORIG_GID" ]; then
    groupmod -g "$CONTAINER_USERGID" test
    usermod -u "$CONTAINER_USERUID" -g "$CONTAINER_USERGID" test
fi
if [ $(stat -c '%u' "${ORIG_HOME}") != "${CONTAINER_USERUID}" -o $(stat -c '%g' "${ORIG_HOME}") != "${CONTAINER_USERGID}" ]; then
    chown "${CONTAINER_USERUID}":"${CONTAINER_USERGID}" "${ORIG_HOME}"
    chown -R "${CONTAINER_USERUID}":"${CONTAINER_USERGID}" "${ORIG_HOME}"/.*
fi

trap clean_up TERM

#echo "[`date`] Starting Memcached..."
#service memcached start

#echo "[`date`] Starting Solr..."
#service solr start

#echo "[`date`] Starting the Web server..."
#service apache2 start

if [ "${COMPOSE_SETUP_APP_ON_BOOT}" != 'skip' ]; then

    # We hash the name of the vendor folder based on packages to install. This allows quick swaps
    if [ -L /home/test/ezmigrationbundle/vendor -o ! -d /home/test/ezmigrationbundle/vendor ]; then
        HASH=$(echo "${EZ_PACKAGES}" | md5sum | awk  '{print $1}')
        if [ ! -d /home/test/ezmigrationbundle/vendor_${HASH} ]; then
            mkdir /home/test/ezmigrationbundle/vendor_${HASH}
        fi
        chown -R test:test /home/test/ezmigrationbundle/vendor_${HASH}
        if [ -L /home/test/ezmigrationbundle/vendor ]; then
            rm /home/test/ezmigrationbundle/vendor
        fi
        ln -s /home/test/ezmigrationbundle/vendor_${HASH} /home/test/ezmigrationbundle/vendor
    fi

    # @todo try to reinstall if last install did fail, even if /tmp/setup_ok does exist...
    # @todo we should as well reinstall if current env vars (packages and other build-config vars) are changed since we installed...
    if [ "${COMPOSE_SETUP_APP_ON_BOOT}" = 'force' -o ! -f /tmp/setup_ok ]; then
        echo "[`date`] Setting up eZ..."
        su test -c "cd /home/test/ezmigrationbundle && ./Tests/environment/setup.sh; echo \$? > /tmp/setup_ok"
    fi
fi

echo "[`date`] Bootstrap finished" | tee /var/run/bootstrap_ok

tail -f /dev/null &
child=$!
wait "$child"
