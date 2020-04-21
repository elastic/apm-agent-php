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

#include "php_elasticapm.h"
#include "util.h"
#include "log.h"


static inline String elasticApmGetCurrentTransactionId()
{
    const Transaction* const currentTransaction = getGlobalTracer()->currentTransaction;
    const String result = currentTransaction == NULL ? NULL : currentTransaction->id;

    ELASTICAPM_LOG_TRACE( "Result: %s", result == NULL ? "NULL" : result );
    return result;
}

static inline String elasticApmGetCurrentTraceId()
{
    const Transaction* const currentTransaction = getGlobalTracer()->currentTransaction;
    const String result = currentTransaction == NULL ? NULL : currentTransaction->traceId;

    ELASTICAPM_LOG_TRACE( "Result: %s", result == NULL ? "NULL" : result );
    return result;
}

static inline bool elasticApmIsEnabled()
{
    const bool result = getTracerCurrentConfigSnapshot( getGlobalTracer() )->enabled;

    ELASTICAPM_LOG_TRACE( "Result: %s", boolToString( result ) );
    return result;
}

static inline ResultCode elasticApmGetConfigOption( String optionName, zval* return_value )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "optionName: `%s'", optionName );

    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String streamedParsedValue = NULL;
    const ResultCode resultCode = getConfigManagerOptionValueByName(
            getGlobalTracer()->configManager,
            optionName,
            /* parsedValueAsZval: */ return_value,
            &txtOutStream,
            &streamedParsedValue );

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG(
            "ResultCode: %s. Option's: name: `%s', value: %s",
            resultCodeToString( resultCode ), optionName, streamedParsedValue );
    return resultCode;
}
