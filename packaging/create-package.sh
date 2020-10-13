#!/usr/bin/env sh
set -x

## so files manipulation to avoid specific platform so files
##   - If alpine then use only alpine so files
##   - If deb/rpm then skip alpine so files
##   - If tar then use all the so files
BUILD_EXT_DIR=build/ext/modules/
mkdir -p ${BUILD_EXT_DIR}
cp -rf src/ext/modules/*.so ${BUILD_EXT_DIR}
if [ "${TYPE}" = 'apk' ] ; then
	find ${BUILD_EXT_DIR} -type f -name '*.so' ! -name '*-alpine.so' -delete
elif [ "${TYPE}" = 'deb' ] || [ "${TYPE}" = 'rpm' ] ; then
	find ${BUILD_EXT_DIR} -type f -name '*-alpine.so' -delete
fi

## src/ext files to be archived in the package
BUILD_SRC_EXT_DIR=build/src
IGNORE_FILE=${BUILD_SRC_EXT_DIR}/ext/.gitignore
mkdir -p ${BUILD_SRC_EXT_DIR}
cp -rf src/ext ${BUILD_SRC_EXT_DIR}
if [ -e ${IGNORE_FILE} ] ; then
	while IFS= read -r line
	do
		if [ -n "$line" ]; then
			if case $line in "#"*) false;; *) true;; esac; then
				# shellcheck disable=SC2086
				rm -rf ${BUILD_SRC_EXT_DIR}/ext/${line} || true
			fi
		fi
	done < "${IGNORE_FILE}"
	rm ${IGNORE_FILE} || true
fi

## Create package
fpm --input-type dir \
		--output-type "${TYPE}" \
		--name "${NAME}" \
		--version "${VERSION}" \
		--architecture all \
		--url 'https://github.com/elastic/apm-agent-php' \
		--maintainer 'APM Team <info@elastic.co>' \
		--license 'ASL 2.0' \
		--vendor 'Elasticsearch, Inc.' \
		--description "PHP agent for Elastic APM\nGit Commit: ${GIT_SHA}" \
		--package "${OUTPUT}" \
		--chdir /app ${FPM_FLAGS} \
		--after-install=packaging/post-install.sh \
		--before-remove=packaging/before-uninstall.sh \
		packaging/post-install.sh=${PHP_AGENT_DIR}/bin/post-install.sh \
		${BUILD_SRC_EXT_DIR}=${PHP_AGENT_DIR} \
		${BUILD_EXT_DIR}=${PHP_AGENT_DIR}/extensions \
		README.md=${PHP_AGENT_DIR}/docs/README.md \
		src/ElasticApm=${PHP_AGENT_DIR}/src \
		src/bootstrap_php_part.php=${PHP_AGENT_DIR}/src/bootstrap_php_part.php

## Create sha512
BINARY=$(ls -1 "${OUTPUT}"/*."${TYPE}")
SHA=${BINARY}.sha512
sha512sum "${BINARY}" > "${SHA}"
sed -i.bck "s#${OUTPUT}/##g" "${SHA}"
rm "${OUTPUT}"/*.bck
