#!/usr/bin/env sh
set -x

## Create APK v3 package using abuild on Alpine 3.21+
## This produces packages compatible with apk-tools 3.x

PACKAGE_ARCH=""
if [ "${BUILD_ARCH}" = "x86-64" ]; then
	PACKAGE_ARCH="x86_64"
elif [ "${BUILD_ARCH}" = "arm64" ]; then
	PACKAGE_ARCH="aarch64"
else
	echo "Architecture not supported"
	exit 1
fi

BUILD_TARGET="linuxmusl-${BUILD_ARCH}"
BUILD_EXT_DIR=agent/native/_build/${BUILD_TARGET}-release/ext
BUILD_LOADER_DIR=agent/native/_build/${BUILD_TARGET}-release/loader/code

echo "Fetching agent libraries from ${BUILD_EXT_DIR}"
echo "Package architecture ${PACKAGE_ARCH}"

if [ ! -d "${BUILD_EXT_DIR}" ]; then
	echo "Agent libraries not built! Missing folder ${BUILD_EXT_DIR}"
	exit 1
fi

touch build/elastic-apm.ini

# Prepare extensions in temp dir
EXTENSIONS_DIR=/tmp/extensions
mkdir -p ${EXTENSIONS_DIR}
cp ${BUILD_EXT_DIR}/*.so ${EXTENSIONS_DIR}/
cp ${BUILD_LOADER_DIR}/*.so ${EXTENSIONS_DIR}/

# Alpine pkgver does not allow dashes
PKGVER=$(echo ${VERSION} | tr '-' '_')

# Setup APKBUILD workspace
WORK_DIR=/tmp/apk-v3-build
ABUILD_DIR=${WORK_DIR}/${NAME}
rm -rf ${WORK_DIR}
mkdir -p ${ABUILD_DIR}

# Post-install trigger
cat > ${ABUILD_DIR}/${NAME}.post-install << 'EOF'
#!/bin/sh
/opt/elastic/apm-agent-php/bin/post-install.sh
EOF

# Pre-deinstall trigger
cat > ${ABUILD_DIR}/${NAME}.pre-deinstall << 'EOF'
#!/bin/sh
/opt/elastic/apm-agent-php/bin/before-uninstall.sh
EOF

# Create APKBUILD
cat > ${ABUILD_DIR}/APKBUILD << BUILDEOF
# Maintainer: APM Team <info@elastic.co>
pkgname=${NAME}
pkgver=${PKGVER}
pkgrel=0
pkgdesc="PHP agent for Elastic APM (Git Commit: ${GIT_SHA})"
url="https://github.com/elastic/apm-agent-php"
arch="all"
license="Apache-2.0"
depends="bash"
install="\$pkgname.post-install \$pkgname.pre-deinstall"
options="!check !fhs !strip !tracedeps"

package() {
	local dest="\$pkgdir${PHP_AGENT_DIR}"

	install -d "\$dest/extensions"
	for f in ${EXTENSIONS_DIR}/*.so; do
		install -m755 "\$f" "\$dest/extensions/"
	done

	mkdir -p "\$dest/src"
	cp -r /app/agent/php/* "\$dest/src/"

	install -d "\$dest/etc"
	install -m644 /app/build/elastic-apm.ini "\$dest/etc/"
	install -m644 /app/packaging/elastic-apm-custom-template.ini "\$dest/etc/elastic-apm-custom.ini"

	install -d "\$dest/bin"
	install -m755 /app/packaging/post-install.sh "\$dest/bin/post-install.sh"
	install -m755 /app/packaging/before-uninstall.sh "\$dest/bin/before-uninstall.sh"

	install -d "\$dest/docs"
	install -m644 /app/README.md "\$dest/docs/README.md"
}
BUILDEOF

#print debug info
echo "APKBUILD content:"
cat ${ABUILD_DIR}/APKBUILD || true


# Build as non-root user (abuild requirement)
chown -R builder:builder ${WORK_DIR} ${EXTENSIONS_DIR}
OUTPUT_DIR=${WORK_DIR}/output
su builder -c "cd ${ABUILD_DIR} && abuild -F -d -P ${OUTPUT_DIR}"

# Find the built package
APK_FILE=$(find ${OUTPUT_DIR} -name "*.apk" ! -name "APKINDEX*" | head -1)
if [ -z "${APK_FILE}" ]; then
	echo "ERROR: No APK file found in build output"
	ls -laR ${OUTPUT_DIR} || true
	exit 1
fi

# Copy to output with architecture in filename
NEW_NAME="${NAME}-${PKGVER}-r0.${PACKAGE_ARCH}.apk"
mkdir -p ${OUTPUT}
cp ${APK_FILE} ${OUTPUT}/${NEW_NAME}

# Create sha512 checksum
cd ${OUTPUT}
sha512sum "${NEW_NAME}" > "${NEW_NAME}.sha512"

echo "APK v3 package created: ${OUTPUT}/${NEW_NAME}"
