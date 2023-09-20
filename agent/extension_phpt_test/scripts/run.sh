#!/bin/bash

# if you want to run only that one test, execute scipt with test or folder name
# example: docker-compose run --rm  phpt_82 /scripts/run.sh tests/output
# example: docker-compose run --rm  phpt_82 /scripts/run.sh tests/output/ob_start_error_005.phpt
TEST_OR_DIRECTORY_TO_EXECUTE=$1

PHP_VERSION=`php-config --version`
PHP_VERSION=${PHP_VERSION%*.*}
PHP_API_VERSION=""

if [ $PHP_VERSION == "7.2" ]; then
	PHP_API_VERSION="20170718"
elif [ $PHP_VERSION == "7.3" ]; then
	PHP_API_VERSION="20180731"
elif [ $PHP_VERSION == "7.4" ]; then
	PHP_API_VERSION="20190902"
elif [ $PHP_VERSION == "8.0" ]; then
	PHP_API_VERSION="20200930"
elif [ $PHP_VERSION == "8.1" ]; then
	PHP_API_VERSION="20210902"
elif [ $PHP_VERSION == "8.2" ]; then
	PHP_API_VERSION="20220829"
fi

mkdir -m 666 -p /results/${PHP_VERSION}
echo "Making results directory /results/${PHP_VERSION} resultCode: $?" 

RESULT_PREFIX=`date -u +"%Y%m%d_%H%M%S-"`

TEST_FAILED_WITH_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-failed-with-agent.txt
TEST_FAILED_WITH_AGENT_ARCH=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-failed-with-agent.tar.gz
TEST_FAILED_WITHOUT_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-failed-without-agent.txt
TEST_FAILED_WITHOUT_AGENT_ARCH=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-failed-without-agent.tar.gz
TEST_RESULTS_WITH_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-results-with-agent.txt
TEST_RESULTS_WITHOUT_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-results-without-agent.txt
TEST_SEGFAULTS_WITH_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-segfaults-with-agent.txt
TEST_SEGFAULTS_WITHOUT_AGENT=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-segfaults-without-agent.txt

TEST_RESULTS_MD=/results/${PHP_VERSION}/${RESULT_PREFIX}tests-result.md

TEST_ALLOWED_TO_FAIL=/allowedToFailLists/allowedToFail${PHP_VERSION}.txt
TEST_FAILURES_BASELINE=/baselineFailures/baselineFailures${PHP_VERSION}.txt

printf --  '-%.0s' {1..80} && echo ""
echo "Elastic PHP agent configuration"
printf --  '-%.0s' {1..80} && echo ""

cat /usr/local/etc/php/conf.d/99-elastic.ini

function cleanup() {
	shopt -s globstar
	for TEST in tests/**/*.exp; do
		if [ "${TEST}" == "tests/**/*.exp" ]; then
			continue
		fi

		BASE="${TEST%.*}"
		rm ${BASE}.diff ${BASE}.exp ${BASE}.log ${BASE}.out ${BASE}.php ${BASE}.sh
	done
}

function compress_test_results() {
	OUTPUT=$1
	FILES_TO_COMPRESS=$(mktemp)
	shopt -s globstar

	EXTENSIONS=( "diff" "exp" "log" "out" "php" "phpt" "sh" )

	for TEST in **/*.exp; do
		if [ "${TEST}" == "**/*.exp" ]; then
			continue
		fi

		BASE="${TEST%.*}"
		for EXTENSION in "${EXTENSIONS[@]}"
		do
			if [ -f "${BASE}.${EXTENSION}" ]; then
				echo "${BASE}.${EXTENSION}" >>${FILES_TO_COMPRESS}
			fi
		done
	done
	tar -czf ${OUTPUT} -T ${FILES_TO_COMPRESS}
	rm ${FILES_TO_COMPRESS}
}

function wait_for_endpoint() {
	ENDPOINT=$1
	MAXRETRIES=$2

	RETRY=0
	while [ $(curl --write-out %{http_code} --silent --output /dev/null ${ENDPOINT}) != 200 ]; do
		sleep 1
		((RETRY++))
		echo "waiting for ${ENDPOINT}, retry: ${RETRY}/${MAXRETRIES}"
		if [ ${RETRY} -ge ${MAXRETRIES} ]; then
			break
		fi
	done

}

wait_for_endpoint http://elasticsearch:9200/ 30
wait_for_endpoint http://apm-server:8200/ 30

cd /usr/src/php

printf --  '-%.0s' {1..80} && echo ""
echo "Running tests without agent"
printf --  '-%.0s' {1..80} && echo ""

cleanup
TEST_PHP_EXECUTABLE=/usr/local//bin/php /usr/local/bin/php -n ./run-tests.php -q -x --offline -w "${TEST_FAILED_WITHOUT_AGENT}" -W "${TEST_RESULTS_WITHOUT_AGENT}" ${TEST_OR_DIRECTORY_TO_EXECUTE}
grep -Rl "Segmentation fault (core dumped)" --include=*.log | sed 's|\.log$|.phpt|' >${TEST_SEGFAULTS_WITHOUT_AGENT}
compress_test_results ${TEST_FAILED_WITHOUT_AGENT_ARCH}

printf --  '-%.0s' {1..80} && echo ""
echo "Running tests with agent"
printf --  '-%.0s' {1..80} && echo ""

cleanup
TEST_PHP_EXECUTABLE=/usr/local/bin/php /usr/local/bin/php -n ./run-tests.php -q -x --offline -w "${TEST_FAILED_WITH_AGENT}" -W "${TEST_RESULTS_WITH_AGENT}" -d "extension=/opt/elastic/elastic_apm-${PHP_API_VERSION}.so" ${TEST_OR_DIRECTORY_TO_EXECUTE}
grep -Rl "Segmentation fault (core dumped)" --include=*.log | sed 's|\.log$|.phpt|' >${TEST_SEGFAULTS_WITH_AGENT}
compress_test_results ${TEST_FAILED_WITH_AGENT_ARCH}

/scripts/processResults.php --test_folder="/usr/src/php/tests" --allowed ${TEST_ALLOWED_TO_FAIL} --baseline ${TEST_FAILURES_BASELINE} \
 --failed_with_agent ${TEST_FAILED_WITH_AGENT} --failed_without_agent ${TEST_FAILED_WITHOUT_AGENT} \
 --results_with_agent ${TEST_RESULTS_WITH_AGENT} --results_without_agent ${TEST_RESULTS_WITHOUT_AGENT} \
 --segfaults_with_agent ${TEST_SEGFAULTS_WITH_AGENT} --segfaults_without_agent ${TEST_SEGFAULTS_WITHOUT_AGENT} \
 --markdown ${TEST_RESULTS_MD}