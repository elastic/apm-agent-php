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
#include <zend.h>
#include <zend_API.h>
#include <zend_modules.h>

#include "Tracer.h"
#include "elastic_apm_version.h"

extern zend_module_entry elastic_apm_module_entry;

#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

ZEND_BEGIN_MODULE_GLOBALS(elastic_apm)
    Tracer globalTracer;
ZEND_END_MODULE_GLOBALS(elastic_apm)

ZEND_EXTERN_MODULE_GLOBALS(elastic_apm)

ResultCode registerElasticApmIniEntries( int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
void unregisterElasticApmIniEntries( int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
