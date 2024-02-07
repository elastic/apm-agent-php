#!/usr/bin/env bash
set -xe

## Location for the generated test report files
APP_FOLDER=/app


## Fetch PHP api version to be added to the so file that has been generated
PHP_API=$(php -i | grep -i 'PHP API' | sed -e 's#.* =>##g' | awk '{print $1}')
PHP_EXECUTABLE=$(which php)

thisScriptDir="$( dirname "${BASH_SOURCE[0]}" )"
thisScriptDir="$( realpath "${thisScriptDir}" )"
source "${thisScriptDir}/shared.sh"

function onScriptExit () {
    if [ -n "${scriptFinishedSuccessfully}" ] && [ "${scriptFinishedSuccessfully}" == "true" ] ; then
        local shouldPrintTheMostRecentSyslogFile=false
    else
        local shouldPrintTheMostRecentSyslogFile=true
    fi

    copySyslogFilesAndPrintTheMostRecentOne ${shouldPrintTheMostRecentSyslogFile}
    if [ -n "${CHOWN_RESULTS_UID}" ] && [ -n "${CHOWN_RESULTS_GID}" ]; then
        chown --recursive "${CHOWN_RESULTS_UID}:${CHOWN_RESULTS_GID}" "${APP_FOLDER}"
    fi
}

trap onScriptExit EXIT

ensureSyslogIsRunning

if [ -z "$BUILD_ARCHITECTURE" ]
then
      echo "\$BUILD_ARCHITECTURE is not specified, assuming linux-x86-64"
      BUILD_ARCHITECTURE=linux-x86-64
fi
echo "BUILD ARCHITECTURE: $BUILD_ARCHITECTURE"


cd $APP_FOLDER/agent/native/ext
mkdir -p $APP_FOLDER/build

AGENT_EXTENSION_DIR=$APP_FOLDER/agent/native/_build/$BUILD_ARCHITECTURE-release/ext
AGENT_LOADER_DIR=$APP_FOLDER/agent/native/_build/$BUILD_ARCHITECTURE-release/loader/code

# copy loader library
cp ${AGENT_LOADER_DIR}/elastic_apm_loader.so ${AGENT_EXTENSION_DIR}/

AGENT_EXTENSION=$AGENT_EXTENSION_DIR/elastic_apm_loader.so

ls -al $AGENT_EXTENSION_DIR

RUN_TESTS=./tests/run-tests.php

#Disable agent for auxiliary PHP processes to reduce noise in logs
export ELASTIC_APM_ENABLED=false
for phptFile in ./tests/*.phpt; do
    msg="Running tests in \`${phptFile}' ..."
    echo "${msg}"
    this_script_name="$( basename "${BASH_SOURCE[0]}" )"
    logger -t "${this_script_name}" "${msg}"

    # Disable exit-on-error
    # set +e
    TESTS="--show-all ${phptFile}"


    top_srcdir=$APP_FOLDER/agent/native/ext

    INI_FILE=`php -d 'display_errors=stderr' -r 'echo php_ini_loaded_file();' 2> /dev/null`

    if test "$INI_FILE"; then
        egrep -h -v $PHP_DEPRECATED_DIRECTIVES_REGEX "$INI_FILE" > /tmp/tmp-php.ini
    else
        echo > /tmp/tmp-php.ini
    fi

    TEST_PHP_SRCDIR=$top_srcdir TEST_PHP_EXECUTABLE=$PHP_EXECUTABLE $PHP_EXECUTABLE -n -c /tmp/tmp-php.ini $PHP_TEST_SETTINGS $RUN_TESTS -n -c /tmp/tmp-php.ini -d extension_dir=$AGENT_EXTENSION_DIR -d extension=$AGENT_EXTENSION  $PHP_TEST_SHARED_EXTENSIONS $TESTS
    exitCode=$?
    rm /tmp/tmp-php.ini


    if [ ${exitCode} -ne 0 ] ; then
        echo "Tests in \`${phptFile}' failed"
        phptFileName="${phptFile%.phpt}"
        cat "${phptFileName}.log"
        cat "${phptFileName}.out"
        exit 1
    fi

    # Re-enable exit-on-error
    set -e
done


# Re-enable exit-on-error
set -e

cd $APP_FOLDER

scriptFinishedSuccessfully=true