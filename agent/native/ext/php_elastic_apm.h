/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include "Tracer.h"
#include "elastic_apm_version.h"

#include "AgentGlobals.h"

#include "PhpErrorData.h"

#include <php.h>
#include <zend.h>
#include <zend_API.h>
#include <zend_modules.h>

#include <memory>


extern zend_module_entry elastic_apm_module_entry;

#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
ZEND_TSRMLS_CACHE_EXTERN()
#endif



ZEND_BEGIN_MODULE_GLOBALS(elastic_apm)
    Tracer globalTracer;
    elasticapm::php::AgentGlobals *globals;
    zval lastException;
    std::unique_ptr<elasticapm::php::PhpErrorData> lastErrorData;
    bool captureErrors;
ZEND_END_MODULE_GLOBALS(elastic_apm)

ZEND_EXTERN_MODULE_GLOBALS(elastic_apm)

#ifdef ZTS
#define ELASTICAPM_G(member) ZEND_MODULE_GLOBALS_ACCESSOR(elastic_apm, member)
#else
#define ELASTICAPM_G(member) (elastic_apm_globals.member)
#endif


ResultCode registerElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
void unregisterElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
