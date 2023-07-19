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

#include "elastic_apm_assert.h"
#include "log.h"
#include "ConfigManager.h"

#if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_ASSERT

const char* assertLevelNames[ numberOfAssertLevels ] =
{
    [ assertLevel_off ] = "OFF",
    [ assertLevel_O_1 ] = "O_1",
    [ assertLevel_O_n ] = "O_n",
    [ assertLevel_all ] = "ALL"
};

void elasticApmAbort()
{
    #ifdef PHP_WIN32
    // Disable the abort message box
    if ( ! getGlobalCurrentConfigSnapshot()->allowAbortDialog ) _set_abort_behavior( 0, _WRITE_ABORT_MSG );
    #endif
    abort();
}

void vElasticApmAssertFailed(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , va_list msgPrintfFmtArgs
)
{
    vLogWithLogger( getGlobalLogger()
                    , /* isForced: */ true
                    , logLevel_critical
                    , ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_CURRENT_LOG_CATEGORY )
                    , makeStringViewFromString( filePath )
                    , lineNumber
                    , makeStringViewFromString( funcName )
                    , msgPrintfFmt
                    , msgPrintfFmtArgs );

    void* stackTraceAddresses[ maxCaptureStackTraceDepth ];
    size_t stackTraceAddressesCount = 0;
    stackTraceAddressesCount = ELASTIC_APM_CAPTURE_STACK_TRACE( &(stackTraceAddresses[ 0 ]), ELASTIC_APM_STATIC_ARRAY_SIZE( stackTraceAddresses ) );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE * 10 ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_FORCE_LOG_CRITICAL( "Stack trace:\n%s", streamStackTrace( &( stackTraceAddresses[ 0 ] ), stackTraceAddressesCount, /* linePrefix: */ "\t", &txtOutStream ) );

    elasticApmAbort();
}

void elasticApmAssertFailed(
        const char* filePath
        , unsigned int lineNumber
        , const char* funcName
        , const char* msgPrintfFmt
        , /* msgPrintfFmtArgs */ ...
)
{
    va_list msgPrintfFmtArgs;
    va_start( msgPrintfFmtArgs, msgPrintfFmt );

    vElasticApmAssertFailed( filePath
                             , lineNumber
                             , funcName
                             , msgPrintfFmt
                             , msgPrintfFmtArgs );

    va_end( msgPrintfFmtArgs );
}

AssertLevel internalChecksToAssertLevel( InternalChecksLevel internalChecksLevel )
{
    ELASTIC_APM_STATIC_ASSERT( assertLevel_not_set == internalChecksLevel_not_set );
    ELASTIC_APM_STATIC_ASSERT( numberOfAssertLevels <= numberOfInternalChecksLevels );

    ELASTIC_APM_ASSERT( ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( internalChecksLevel_not_set, internalChecksLevel, internalChecksLevel_all )
                       , "internalChecksLevel: %d", internalChecksLevel );

    if ( internalChecksLevel >= internalChecksLevel_all ) return assertLevel_all;
    if ( internalChecksLevel < ( assertLevel_all - 1 ) ) return (AssertLevel)internalChecksLevel;
    return (AssertLevel)( assertLevel_all - 1 );
}

String streamAssertLevel( AssertLevel level, TextOutputStream* txtOutStream )
{
    if ( level == assertLevel_not_set )
        return streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfAssertLevels )
        return streamInt( level, txtOutStream );

    return streamString( assertLevelNames[ level ], txtOutStream );
}

#endif // #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
