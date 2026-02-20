#!/usr/bin/env bash
set -e

#
# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# *) .ci/shared.sh (this file; update ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS below)
# *) .github/workflows/test.yml (update jobs -> test -> strategy -> matrix -> php-version)
# *) .github/workflows/phpt.yml
# *) .github/workflows/loop.yml (update jobs -> loop-matrix -> strategy -> matrix -> php-version)
# *) .ci/packer_cache.sh (the list of PHP versions might appear more than once - search for "list of PHP versions")
# *) packaging/post-install.sh (the list of PHP versions might appear more than once - search for "list of PHP versions")
# *) composer.json
# *) tests/ElasticApmTests/ComponentTests/GenerateUnpackScriptsTest.php (search for "list of PHP versions")
# *) CMakeList.txt and conan dependencies
# *) phpdetection.cpp in agent loader
# *) docker-compose.yml in packaging/test

#
export ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS=(7.2 7.3 7.4 8.0 8.1 8.2 8.3 8.4 8.5)

export ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_NATIVE_PACKAGE_TYPES=(apk deb rpm)
export ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_PACKAGE_TYPES=("${ELASTIC_APM_PHP_TESTS_SUPPORTED_LINUX_NATIVE_PACKAGE_TYPES[@]}" tar)

export ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_LEAF_KINDS_SHORT_NAMES=(http cli)
export ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_KINDS_SHORT_NAMES=("${ELASTIC_APM_PHP_TESTS_APP_CODE_HOST_LEAF_KINDS_SHORT_NAMES[@]}" all)

export ELASTIC_APM_PHP_TESTS_LEAF_GROUPS_SHORT_NAMES=(no_ext_svc with_ext_svc)
export ELASTIC_APM_PHP_TESTS_GROUPS_SHORT_NAMES=("${ELASTIC_APM_PHP_TESTS_LEAF_GROUPS_SHORT_NAMES[@]}" smoke)

function ensureSyslogIsRunningImpl () {
    if ps -ef | grep -v 'grep' | grep -q 'syslogd' ; then
        echo 'Syslog is already started.'
        return
    fi

    if which rsyslogd; then
        if [[ -f /etc/rsyslog.conf ]]; then
            if grep -q /var/log/messages /etc/rsyslog.conf ; then
                sed -i '/\/var\/log\/messages/s/messages/syslog/' /etc/rsyslog.conf
            fi
        fi
        rsyslogd
    else
        if which syslogd; then
            syslogd
        else
            echo 'syslog is not installed'
            exit 1
        fi
    fi
}

function ensureSyslogIsRunning () {
    ensureSyslogIsRunningImpl
    ps -ef | grep -v 'grep' | grep 'syslogd'
}

function copySyslogFilesAndPrintTheMostRecentOne () {
    local shouldPrintTheMostRecentSyslogFile="${1:?}"
    echo "Content of /var/log/"
    ls -l /var/log/
    local possibleSyslogFiles=(/var/log/messages /var/log/syslog)
    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            local syslogCopyDir=/app/build/syslog
            mkdir -p "${syslogCopyDir}"
            cp "${syslogFile}"* ${syslogCopyDir}
            echo "syslog files (${syslogFile}*) copied to ${syslogCopyDir}"
            chmod -R 755 ${syslogCopyDir}
        fi
    done

    if [ "${shouldPrintTheMostRecentSyslogFile}" == "true" ] ; then
        for syslogFile in "${possibleSyslogFiles[@]}"
        do
            if [[ -f "${syslogFile}" ]]; then
                echo "Content of the most recent syslog file (${syslogFile}):"
                cat "${syslogFile}"
            fi
        done
    fi
}

function runPhpCoposerInstall () {
    local run_command_with_timeout_and_retries_args=(--max-tries=3)
    run_command_with_timeout_and_retries_args=(--retry-on-error=yes "${run_command_with_timeout_and_retries_args[@]}")
    local initialTimeoutInMinutes=5
    local initialTimeoutInSeconds=$((initialTimeoutInMinutes*60))
    run_command_with_timeout_and_retries_args=(--timeout="${initialTimeoutInSeconds}" "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--increase-timeout-exponentially=yes "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--wait-time-before-retry="${initialTimeoutInSeconds}" "${run_command_with_timeout_and_retries_args[@]}")

    set +e
    .ci/run_command_with_timeout_and_retries.sh "${run_command_with_timeout_and_retries_args[@]}" -- composer install
    local composerCommandExitCode=$?
    set -e

    if [ ${composerCommandExitCode} -ne 0 ] ; then
        exit ${composerCommandExitCode}
    fi
}
