#!/usr/bin/env bash

set -xe

echo "args: $*"
USE_VALGRIND=$1
echo "USE_VALGRIND: ${USE_VALGRIND}"

if [ "${USE_VALGRIND}" != "true" ] ; then
    exit 0
fi

apt-get -qq update
apt-get -qq install valgrind
which valgrind

php_fpm_binary_path=/usr/local/sbin/php-fpm
php_fpm_copy_of_original_binary_path="${php_fpm_binary_path}-original"
mv "${php_fpm_binary_path}" "${php_fpm_copy_of_original_binary_path}"
cp /app/php-fpm_under_valgrind.sh "${php_fpm_binary_path}"
chmod +x "${php_fpm_binary_path}"
cat "${php_fpm_binary_path}"
