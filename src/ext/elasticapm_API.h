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

ResultCode elasticApmInterceptCallsToInternalMethod( String className, String methodName, uint32_t* interceptRegistrationId );

ResultCode elasticApmInterceptCallsToInternalFunction( String functionName, uint32_t* interceptRegistrationId );

void elasticApmCallInterceptedOriginal( zval* return_value );

void resetCallInterceptionOnRequestShutdown();

ResultCode elasticApmSendToServer( StringView serializedMetadata, StringView serializedEvents );
