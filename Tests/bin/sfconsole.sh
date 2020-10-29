#!/usr/bin/env bash

source $(dirname ${BASH_SOURCE[0]})/set-env-vars.sh

# @todo should we move the definition of CONSOLE_CMD here ?

php $CONSOLE_CMD "$@"
