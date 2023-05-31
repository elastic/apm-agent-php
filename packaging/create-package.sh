#!/usr/bin/env sh
set -x

## so files manipulation to avoid specific platform so files
##   - If alpine then use only alpine so files
##   - If deb/rpm then skip alpine so files
##   - If tar then use all the so files

BUILD_EXT_DIR=""

if [ "${TYPE}" = 'apk' ] ; then
	BUILD_EXT_DIR=build/ext/linuxmusl-x86-64/
else
	BUILD_EXT_DIR=build/ext/linux-x86-64/
fi

touch build/elastic-apm.ini

function createPackage () {

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
		--directories ${PHP_AGENT_DIR}/etc \
		--config-files ${PHP_AGENT_DIR}/etc \
		--exclude *.debug \
		packaging/post-install.sh=${PHP_AGENT_DIR}/bin/post-install.sh \
		build/elastic-apm.ini=${PHP_AGENT_DIR}/etc/ \
		packaging/elastic-apm-custom-template.ini=${PHP_AGENT_DIR}/etc/elastic-apm-custom.ini \
		packaging/before-uninstall.sh=${PHP_AGENT_DIR}/bin/before-uninstall.sh \
		agent/php/=${PHP_AGENT_DIR}/src \
		${BUILD_EXT_DIR}=${PHP_AGENT_DIR}/extensions \
		README.md=${PHP_AGENT_DIR}/docs/README.md

## Create sha512
BINARY=$(ls -1 "${OUTPUT}"/${NAME}*."${TYPE}")
SHA=${BINARY}.sha512
sha512sum "${BINARY}" > "${SHA}"
sed -i.bck "s#${OUTPUT}/##g" "${SHA}"
rm "${OUTPUT}"/*.bck

}


function createDebugPackage () {

fpm --input-type dir \
		--output-type "${TYPE}" \
		--name "${NAME}" \
		--version "${VERSION}" \
		--architecture all \
		--url 'https://github.com/elastic/apm-agent-php' \
		--maintainer 'APM Team <info@elastic.co>' \
		--license 'ASL 2.0' \
		--vendor 'Elasticsearch, Inc.' \
		--description "PHP debug symbols agent for Elastic APM\nGit Commit: ${GIT_SHA}" \
		--package "${OUTPUT}" \
		--chdir /app ${FPM_FLAGS} \
		--exclude *.so \
		${BUILD_EXT_DIR}=${PHP_AGENT_DIR}/extensions

## Create sha512
BINARY=$(ls -1 "${OUTPUT}"/${NAME}*."${TYPE}")
SHA=${BINARY}.sha512
sha512sum "${BINARY}" > "${SHA}"
sed -i.bck "s#${OUTPUT}/##g" "${SHA}"
rm "${OUTPUT}"/*.bck

}




# create second tar for musl
if [ "${TYPE}" = 'tar' ] ; then
	NAME_BACKUP=${NAME}
	NAME="${NAME_BACKUP}-linux-x86-64"
	BUILD_EXT_DIR=build/ext/linux-x86-64/
	createPackage

	NAME="${NAME_BACKUP}-debugsymbols-linux-x86-64"
	createDebugPackage

	NAME="${NAME_BACKUP}-linuxmusl-x86-64"
	BUILD_EXT_DIR=build/ext/linuxmusl-x86-64/
	createPackage

	NAME="${NAME_BACKUP}-debugsymbols-linuxmusl-x86-64"
	createDebugPackage
else
	createPackage
fi