#!/usr/bin/env bash

# Shortcut to manage the whole set of containers and run tests

# @todo add as separate actions of this command the clean up of dead images as well as logs and data
# @todo add support for loading an override.env file before launching docker & docker-compose, and/or set up
#       file Tests/docker/data/.composer/auth.json
# @todo add support for building and starting the containers without provisioning

# consts
WEBSVC=ez
WEBUSER=test
# vars
REPROVISION=false
NOPROVISION=false
REBUILD=false
CLEANUPIMAGES=false
DOCKER_NO_CACHE=

function help() {
    echo -e "Usage: test.sh [OPTION] COMMAND

Manages the Test Environment Docker Stack

Commands:
    build           build or rebuild the complete set of containers and set up eZ. Leaves the stack running
    enter           enter the test container
    exec \$cmd       execute a command in the test container
    runtests        execute the whole test suite using the test container
    images          list container images
    logs            view output from containers
    ps              show the status of running containers
    provision       set up eZ without rebuilding the containers first
    resetdb         resets the database used for testing (normally executed as part of provisioning)
    start           start the complete set of containers
    stop            stop the complete set of containers
    top             display the running processes

Options:
    -c              clean up docker images which have become useless - when running 'build'
    -h              print help
    -p              force full app provisioning (via resetting containers to clean-build status besides updating them if needed) - when running 'build'
    -r              force containers to rebuild from scratch (this forces a full app provisioning as well) - when running 'build'
    -z              avoid using docker cache - when running 'build -r'
"
}

function build() {
    if [ $CLEANUPIMAGES = 'true' ]; then
        # for good measure, do a bit of hdd disk cleanup ;-)
        echo "[`date`] Removing dead Docker images from disk..."
        docker rmi $(docker images | grep "<none>" | awk "{print \$3}")
    fi

    echo "[`date`] Building all Containers..."

    docker-compose stop
    if [ $REBUILD = 'true' ]; then
        docker-compose rm -f
    fi

    docker-compose build ${DOCKER_NO_CACHE}

    # @todo...
    #if [ $NOPROVISION = 'true' ]; then
    #fi

    echo "[`date`] Starting all Containers..."

    if [ $REPROVISION = 'true' ]; then
        docker-compose up -d --force-recreate
    else
        docker-compose up -d
    fi

    if [ $CLEANUPIMAGES = 'true' ]; then
        echo "[`date`] Removing dead Docker images from disk, again..."
        docker rmi $(docker images | grep "<none>" | awk "{print \$3}")
    fi

    #if [ $NOPROVISION != 'true' ]; then

        until docker exec ${WEBCONTAINER} cat /var/run/bootstrap_ok 2>/dev/null; do
            echo "[`date`] Waiting for the Test container to be fully set up..."
            sleep 5
        done

    #fi

    echo "[`date`] Build finished"
}

function provision() {
    echo "[`date`] Starting all Containers..."
    docker-compose up -d

    until docker exec ${WEBCONTAINER} cat /var/run/bootstrap_ok 2>/dev/null; do
        echo "[`date`] Waiting for the Test container to be fully started..."
        sleep 5
    done

    docker exec ${WEBCONTAINER} rm /var/run/setup_ok
    echo "[`date`] Setting up eZ..."
    su test -c "cd /home/test/ezmigrationbundle && ./Tests/environment/setup.sh && echo 0 > /var/run/setup_ok"

    echo "[`date`] Provisioning finished"
}

function start() {
    echo "[`date`] Starting all Containers..."
    docker-compose up -d

    until docker exec ${WEBCONTAINER} cat /var/run/bootstrap_ok 2>/dev/null; do
        echo "[`date`] Waiting for the Test container to be fully started..."
        sleep 5
    done

    echo "[`date`] Startup finished"
}

while getopts ":chprz" opt
do
    case $opt in
        c)
            CLEANUPIMAGES=true
        ;;
        h)
            help
            exit 0
        ;;
        #n)
        #    NOPROVISION=true
        #;;
        p)
            REPROVISION=true
        ;;
        r)
            REBUILD=true
        ;;
        z)
            DOCKER_NO_CACHE=--no-cache
        ;;
        \?)
            echo -e "\n\e[31mERROR: unknown option -${OPTARG}\e[0m\n" >&2
            help
            exit 1
        ;;
    esac
done
shift $((OPTIND-1))

which docker >/dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "\n\e[31mPlease install docker & add it to \$PATH\e[0m\n" >&2
    exit 1
fi

which docker-compose >/dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "\n\e[31mPlease install docker-compose & add it to \$PATH\e[0m\n" >&2
    exit 1
fi

ACTION=$1

cd $(dirname ${BASH_SOURCE[0]})/docker

COMPOSEPROJECT=$(fgrep COMPOSE_PROJECT_NAME .env | sed 's/COMPOSE_PROJECT_NAME=//')
if [ -z "${COMPOSEPROJECT}" ]; then
    echo -e "\n\e[31mCan not find the name of the composer project name in .env\e[0m\n"
    exit 1
fi
WEBCONTAINER="${COMPOSEPROJECT}_${WEBSVC}"

case "$ACTION" in
    build)
        build
    ;;

    enter)
        docker exec -ti ${WEBCONTAINER} su ${WEBUSER}
    ;;

    exec)
        # scary line ? found it at https://stackoverflow.com/questions/12343227/escaping-bash-function-arguments-for-use-by-su-c
        docker exec -ti ${WEBCONTAINER} su ${WEBUSER} -c '"$0" "$@"' -- "$@"
    ;;

    images)
        docker-compose images
    ;;

    logs)
        docker-compose logs
    ;;

    provision)
        provision
    ;;

    ps)
        docker-compose ps
    ;;

    resetdb)
        docker exec -ti ${WEBCONTAINER} su ${WEBUSER} -c './Tests/environment/create-db.sh'
    ;;

    runtests)
        docker exec -ti ${WEBCONTAINER} su ${WEBUSER} -c './vendor/phpunit/phpunit/phpunit --stderr --colors Tests/phpunit'
    ;;

    start)
        start
    ;;

    stop)
        docker-compose stop
    ;;

    top)
        docker-compose top
    ;;

    *)
        echo -e "\n\e[31mERROR: unknown action '${ACTION}'\e[0m\n" >&2
        help
        exit 1
    ;;
esac
