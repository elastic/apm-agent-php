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

ResultCode registerElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
void unregisterElasticApmIniEntries( int type, int module_number, IniEntriesRegistrationState* iniEntriesRegistrationState );
