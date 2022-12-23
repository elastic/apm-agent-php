#!/usr/bin/env bash

set -xe

php_fpm_binary_path=/usr/local/sbin/php-fpm
php_fpm_copy_of_original_binary_path="${php_fpm_binary_path}-original"

php_fpm_cmd_line_prefix=("${php_fpm_copy_of_original_binary_path}")
export USE_ZEND_ALLOC=0
which valgrind
valgrind_cmd_opts=(--trace-children=yes)
valgrind_cmd_opts=(--undef-value-errors=no "${valgrind_cmd_opts[@]}")
valgrind_cmd_opts=(--gen-suppressions=all "${valgrind_cmd_opts[@]}")
php_fpm_cmd_line_prefix=(valgrind "${valgrind_cmd_opts[@]}" -- "${php_fpm_cmd_line_prefix[@]}")

"${php_fpm_cmd_line_prefix[@]}" "$@"
