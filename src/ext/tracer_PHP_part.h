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

#include "ResultCode.h"
#include "ConfigManager.h"

ResultCode bootstrapTracerPhpPart( const ConfigSnapshot* config, const TimePoint* requestInitStartTime );

void shutdownTracerPhpPart( const ConfigSnapshot* config );

ResultCode tracerPhpPartInterceptedCallPreHook( uint32_t funcToInterceptId, zend_execute_data* execute_data, zval* preHookRetVal );

ResultCode tracerPhpPartInterceptedCallPostHook( uint32_t funcToInterceptId, zval preHookRetVal, zval originalCallRetVal );
