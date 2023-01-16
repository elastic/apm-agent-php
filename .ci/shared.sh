#!/usr/bin/env bash
set -e

#
# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# - generate_package_lifecycle_test_matrix.sh
# - Jenkinsfile (the list appears in Jenkinsfile more than once - search for "list of PHP versions")
#
export ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS=(7.2 7.3 7.4 8.0 8.1)

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

function copySyslogFileAndPrintTheLastOne () {
    local possibleSyslogFiles=(/var/log/messages /var/log/syslog)
    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            local syslogCopyDir=/app/build/syslog
            mkdir -p "${syslogCopyDir}"
            cp "${syslogFile}"* ${syslogCopyDir}
            echo "syslog files (${syslogFile}*) copied to ${syslogCopyDir}"
        fi
    done

    for syslogFile in "${possibleSyslogFiles[@]}"
    do
        if [[ -f "${syslogFile}" ]]; then
            echo "Content of the most recent syslog file (${syslogFile}):"
            cat "${syslogFile}"
        fi
    done
}
