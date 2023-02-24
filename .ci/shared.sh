#!/usr/bin/env bash
set -e

#
# Make sure list of PHP versions supported by the Elastic APM PHP Agent is in sync
# 1) .ci/shared.sh (this file; update ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS below)
# 2) .ci/Jenkinsfile (the list of PHP versions might appear more than once - search for "list of PHP versions")
# 3) .github/workflows/test.yml (update jobs -> test -> strategy -> matrix -> php-version)
# 4) .github/workflows/loop.yml (update jobs -> loop-matrix -> strategy -> matrix -> php-version)
# 5) .ci/packer_cache.sh (the list of PHP versions might appear more than once - search for "list of PHP versions")
# 6) packaging/post-install.sh (the list of PHP versions might appear more than once - search for "list of PHP versions")
# 7) composer.json
# 8) tests/ElasticApmTests/ComponentTests/GenerateUnpackScriptsTest.php (search for "list of PHP versions")
#
export ELASTIC_APM_PHP_TESTS_SUPPORTED_PHP_VERSIONS=(7.2 7.3 7.4 8.0 8.1 8.2)

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
