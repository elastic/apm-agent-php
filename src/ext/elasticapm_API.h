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

#include <stdbool.h>
#include <zend_types.h>
#include "basic_types.h"
#include "StringView.h"
#include "ResultCode.h"

bool elasticApmIsEnabled();

ResultCode elasticApmGetConfigOption( String optionName, zval* return_value );

ResultCode elasticApmInterceptCallsToMethod( String className, String methodName, uint32_t* callToInterceptId );

//ResultCode elasticApmCallInterceptedOriginal( uint32_t funcToInterceptId, uint32_t originalArgsCount, zval* originalArgs );

void resetCallInterceptionOnRequestShutdown();

ResultCode elasticApmSendToServer( String serializedEvents );
