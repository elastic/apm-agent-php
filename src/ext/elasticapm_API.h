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
#include "ResultCode.h"

String elasticApmGetCurrentTransactionId();

String elasticApmGetCurrentTraceId();

bool elasticApmIsEnabled();

ResultCode elasticApmGetConfigOption( String optionName, zval* return_value );

ResultCode elasticApmGetInterceptCallsToPhpFunction( String funcToIntercept
                                                     , String funcToCallBeforeIntercepted
                                                     , String funcToCallAfterIntercepted
);

ResultCode elasticApmGetInterceptCallsToPhpMethod( String className
                                                   , String methodName
                                                   , String preHookFunc
                                                   , String postHookFunc
);
