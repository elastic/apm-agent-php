/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#ifndef ELASTICAPM_DEV_WINDOWS_H
#define ELASTICAPM_DEV_WINDOWS_H

#ifdef ELASTICAPM_DEV_WINDOWS

#include <Zend/zend_config.w32.h>
#include <Zend/zend.h>
#include <Zend/zend_types.h>
#include <Zend/zend_API.h>
#include <Zend/zend_ini.h>
#include <Zend/zend_alloc.h>
#include <Zend/zend_modules.h>

size_t zend_vspprintf(char **pbuf, size_t max_len, const char *format, va_list ap);

#ifdef emalloc
#   undef emalloc
#endif /* #ifdef emalloc */
void* emalloc(size_t size);

#ifdef efree
#   undef efree
#endif /* #ifdef efree */
void efree(void* ptr);

#define TSRMLS_CC

zend_object* zend_throw_exception(zend_class_entry *exception_ce, const char *message, zend_long code);

extern void (*zend_error_cb)(int type, const char *error_filename, const uint32_t error_lineno, const char *format, va_list args);

zend_bool zend_is_auto_global_str(char *name, size_t len);

extern ZEND_API void (*zend_throw_exception_hook)(zval *ex);

extern ZEND_API struct _php_core_globals core_globals;

PHPAPI size_t php_printf(const char *format, ...);

ZEND_API zval* ZEND_FASTCALL zend_hash_str_find(const HashTable *ht, const char *key, size_t len);

#endif /* #ifndef ELASTICAPM_DEV_WINDOWS */

#endif /* #ifndef ELASTICAPM_DEV_WINDOWS_H */
