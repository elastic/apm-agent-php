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

#include "elastic_apm_is_debug_build.h"
#include "basic_types.h" // String

enum InternalChecksLevel
{
    internalChecksLevel_not_set = -1,
    internalChecksLevel_off = 0,

    internalChecksLevel_1,
    internalChecksLevel_2,
    internalChecksLevel_3,

    internalChecksLevel_all,
    numberOfInternalChecksLevels = internalChecksLevel_all + 1
};
typedef enum InternalChecksLevel InternalChecksLevel;

extern const char* internalChecksLevelNames[ numberOfInternalChecksLevels ];

#ifndef ELASTIC_APM_INTERNAL_CHECKS_DEFAULT_LEVEL
#   if ( ELASTIC_APM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTIC_APM_INTERNAL_CHECKS_DEFAULT_LEVEL internalChecksLevel_all
#   else
#       define ELASTIC_APM_INTERNAL_CHECKS_DEFAULT_LEVEL internalChecksLevel_off
#   endif
#endif

struct TextOutputStream;
typedef struct TextOutputStream TextOutputStream;
String streamInternalChecksLevel( InternalChecksLevel level, TextOutputStream* txtOutStream );

InternalChecksLevel getGlobalInternalChecksLevel();
