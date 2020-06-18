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

#include "elasticapm_is_debug_build.h"
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

#ifndef ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL
#   if ( ELASTICAPM_IS_DEBUG_BUILD_01 != 0 )
#       define ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL internalChecksLevel_all
#   else
#       define ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL internalChecksLevel_off
#   endif
#endif

struct TextOutputStream;
typedef struct TextOutputStream TextOutputStream;
String streamInternalChecksLevel( InternalChecksLevel level, TextOutputStream* txtOutStream );

InternalChecksLevel getGlobalInternalChecksLevel();
