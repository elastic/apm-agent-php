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

#pragma once

#include <php.h>
#include <Zend/zend.h>
#include <Zend/zend_API.h>
#include <Zend/zend_modules.h>

#include "GlobalState.h"
#include "elasticapm_version.h"

extern zend_module_entry elasticapm_module_entry;

#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

PHP_FUNCTION(elasticApmGetCurrentTransactionId);
PHP_FUNCTION(elasticApmGetCurrentTraceId);

ZEND_BEGIN_MODULE_GLOBALS(elasticapm)
    GlobalState state;
ZEND_END_MODULE_GLOBALS(elasticapm)

ZEND_EXTERN_MODULE_GLOBALS(elasticapm)

static inline GlobalState* getGlobalState()
{
    return &(ZEND_MODULE_GLOBALS_ACCESSOR(elasticapm, state));
}

static inline const Config* getCurrentConfig()
{
    return &( getGlobalState()->config);
}

void registerElasticApmIniEntries( int module_number);
void unregisterElasticApmIniEntries( int module_number);
