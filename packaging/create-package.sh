#!/usr/bin/env sh
set -x

## so files manipulation to avoid specific platform so files
##   - If alpine then use only alpine so files
##   - If deb/rpm then skip alpine so files
##   - If tar then use all the so files

BUILD_EXT_DIR=""

if [ "${TYPE}" = 'apk' ] ; then
	BUILD_EXT_DIR=agent/native/_build/linuxmusl-x86-64-release/ext/
else
	BUILD_EXT_DIR=agent/native/_build/linux-x86-64-release/ext/
fi

touch build/elastic-apm.ini

function createPackage () {

mkdir -p /tmp/extensions
cp ${BUILD_EXT_DIR}/*.so /tmp/extensions/

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
		${FPM_FLAGS} \
		--after-install=packaging/post-install.sh \
		--before-remove=packaging/before-uninstall.sh \
		--directories ${PHP_AGENT_DIR}/etc \
		--config-files ${PHP_AGENT_DIR}/etc \
		/app/packaging/post-install.sh=${PHP_AGENT_DIR}/bin/post-install.sh \
		/app/build/elastic-apm.ini=${PHP_AGENT_DIR}/etc/ \
		/app/packaging/elastic-apm-custom-template.ini=${PHP_AGENT_DIR}/etc/elastic-apm-custom.ini \
		/app/packaging/before-uninstall.sh=${PHP_AGENT_DIR}/bin/before-uninstall.sh \
		/app/agent/php/=${PHP_AGENT_DIR}/src \
		/tmp/extensions/=${PHP_AGENT_DIR}/extensions \
		/app/README.md=${PHP_AGENT_DIR}/docs/README.md

rm -rf /tmp/extensions

## Create sha512
BINARY=$(ls -1 "${OUTPUT}"/${NAME}*."${TYPE}")
SHA=${BINARY}.sha512
sha512sum "${BINARY}" > "${SHA}"
sed -i.bck "s#${OUTPUT}/##g" "${SHA}"
rm "${OUTPUT}"/*.bck

}


function createDebugPackage () {

mkdir -p /tmp/extensions
cp ${BUILD_EXT_DIR}/*.debug /tmp/extensions/

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
		${FPM_FLAGS} \
		/tmp/extensions/=${PHP_AGENT_DIR}/extensions

rm -rf /tmp/extensions

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
	BUILD_EXT_DIR=agent/native/_build/linux-x86-64-release/ext/
	createPackage

	NAME="${NAME_BACKUP}-debugsymbols-linux-x86-64"
	createDebugPackage

	NAME="${NAME_BACKUP}-linuxmusl-x86-64"
	BUILD_EXT_DIR=agent/native/_build/linuxmusl-x86-64-release/ext/
	createPackage

	NAME="${NAME_BACKUP}-debugsymbols-linuxmusl-x86-64"
	createDebugPackage
else
	createPackage
fi