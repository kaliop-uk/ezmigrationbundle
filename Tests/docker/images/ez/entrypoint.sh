#!/bin/sh

echo "[$(date)] Bootstrapping the Test container..."

clean_up() {
    # Perform program exit housekeeping

    #echo "[$(date)] Stopping the Web server"
    #service apache2 stop

    #echo "[$(date)] Stopping Memcached"
    #service memcached stop

    #echo "[$(date)] Stopping Redis"
    #service redis-server stop

    #echo "[$(date)] Stopping Solr"
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

echo "[$(date)] Fixing filesystem permissions..."

ORIGPASSWD=$(cat /etc/passwd | grep test)
ORIG_UID=$(echo "$ORIGPASSWD" | cut -f3 -d:)
ORIG_GID=$(echo "$ORIGPASSWD" | cut -f4 -d:)
ORIG_HOME=$(echo "$ORIGPASSWD" | cut -f6 -d:)
CONTAINER_USER_UID=${CONTAINER_USER_UID:=$ORIG_UID}
CONTAINER_USER_GID=${CONTAINER_USER_GID:=$ORIG_GID}

if [ "$CONTAINER_USER_UID" != "$ORIG_UID" -o "$CONTAINER_USER_GID" != "$ORIG_GID" ]; then
    groupmod -g "$CONTAINER_USER_GID" test
    usermod -u "$CONTAINER_USER_UID" -g "$CONTAINER_USER_GID" test
fi
if [ $(stat -c '%u' "${ORIG_HOME}") != "${CONTAINER_USER_UID}" -o $(stat -c '%g' "${ORIG_HOME}") != "${CONTAINER_USER_GID}" ]; then
    chown "${CONTAINER_USER_UID}":"${CONTAINER_USER_GID}" "${ORIG_HOME}"
    chown -R "${CONTAINER_USER_UID}":"${CONTAINER_USER_GID}" "${ORIG_HOME}"/.*
fi

if [ "${DB_TYPE}" = postgresql ]; then
    if [ -z "${DB_HOST}" ]; then
        DB_HOST=${DB_TYPE}
    fi
    echo "[$(date)] Setting up ~/.pgpass file..."
    echo "${DB_HOST}:5432:${DB_EZ_DATABASE}:${DB_EZ_USER}:${DB_EZ_PASSWORD}" > "${ORIG_HOME}/.pgpass"
    echo "${DB_HOST}:5432:postgres:postgres:${DB_ROOT_PASSWORD}" >> "${ORIG_HOME}/.pgpass"
    chown "${CONTAINER_USER_UID}":"${CONTAINER_USER_GID}" "${ORIG_HOME}/.pgpass"
    chmod 600 "${ORIG_HOME}/.pgpass"
fi

trap clean_up TERM

#echo "[$(date)] Starting Memcached..."
#service memcached start

#echo "[$(date)] Starting Redis..."
#service redis-server start

#echo "[$(date)] Starting Solr..."
#service solr start

#echo "[$(date)] Starting the Web server..."
#service apache2 start

if [ "${COMPOSE_SETUP_APP_ON_BOOT}" != 'skip' ]; then

    # @todo why not move handling of the 'vendor' symlink to setup.sh ?

    # We hash the name of the vendor folder based on packages to install. This allows quick swaps of vendors
    if [ -z "${COMPOSER_VENDOR_DIR}" ]; then
        P_V=$(php -r 'echo PHP_VERSION;')
        # @todo should we add to the hash calculation a hash of the contents of the original composer.json ?
        # @todo we should add to the hash calculation a hash of the installed php extensions
        # @todo to avoid generating uselessly different variations, we should as well sort EZ_PACKAGES
        COMPOSER_VENDOR_DIR=vendor_$(echo "${P_V} ${EZ_PACKAGES}" | md5sum | awk  '{print $1}')
    fi

    # we assume that /home/test/bundle/vendor is never a file...

    if [ ! -L /home/test/bundle/vendor ]; then
        printf "\n\e[31mWARNING: vendor folder is not a symlink\e[0m\n\n"
    fi

    if [ -L /home/test/bundle/vendor -o ! -d /home/test/bundle/vendor ]; then
        echo "[$(date)] Setting up vendor folder as symlink to ${COMPOSER_VENDOR_DIR}..."

        if [ ! -d /home/test/bundle/${COMPOSER_VENDOR_DIR} ]; then
            mkdir /home/test/bundle/${COMPOSER_VENDOR_DIR}
        fi
        chown -R test:test /home/test/bundle/${COMPOSER_VENDOR_DIR}

        # The double-symlink craze makes it possible to have the 'vendor' symlink on the host disk (mounted as volume),
        # while allowing each container to have it point to a different target 'real' vendor dir which is also on the
        # host disk
        if [ -L /home/test/bundle/vendor ]; then
            TARGET=$(readlink -f /home/test/bundle/vendor)
            if [ "${TARGET}" != "/home/test/bundle/${COMPOSER_VENDOR_DIR}" ]; then
                echo "[$(date)] Fixing vendor folder symlink from ${TARGET} to ${COMPOSER_VENDOR_DIR}..."
                rm /home/test/bundle/vendor
                if [ -L /home/test/local_vendor ]; then
                    rm /home/test/local_vendor
                fi
                ln -s /home/test/bundle/${COMPOSER_VENDOR_DIR} /home/test/local_vendor
                ln -s /home/test/local_vendor /home/test/bundle/vendor
                if [ -f /tmp/setup_ok ]; then rm /tmp/setup_ok; fi
            fi
        else
            echo "[$(date)] Creating vendor folder symlink to ${COMPOSER_VENDOR_DIR}..."
            if [ -L /home/test/local_vendor ]; then
                rm /home/test/local_vendor
            fi
            ln -s /home/test/bundle/${COMPOSER_VENDOR_DIR} /home/test/local_vendor
            ln -s /home/test/local_vendor /home/test/bundle/vendor
            if [ -f /tmp/setup_ok ]; then rm /tmp/setup_ok; fi
        fi
    fi

    # @todo try to reinstall if last install did fail, even if /tmp/setup_ok does exist...
    # @todo we should reinstall as well if current env vars (bundles and other build-config vars) are changed since we installed...
    if [ "${COMPOSE_SETUP_APP_ON_BOOT}" = 'force' -o ! -f /tmp/setup_ok ]; then
        echo "[$(date)] Setting up eZ..."
        su test -c "cd /home/test/bundle && ./Tests/bin/setup.sh; echo \$? > /tmp/setup_ok"
        # back up composer config
        # @todo do not attempt to back up composer.lock if it does not exist
        HASH=${COMPOSER_VENDOR_DIR/vendor_/}
        su test -c "mv /home/test/bundle/composer_last.json /home/test/bundle/composer_${HASH}.json; cp /home/test/bundle/composer.lock /home/test/bundle/composer_${HASH}.lock"
    fi
fi

echo "[$(date)] Bootstrap finished" | tee /var/run/bootstrap_ok

tail -f /dev/null &
child=$!
wait "$child"
