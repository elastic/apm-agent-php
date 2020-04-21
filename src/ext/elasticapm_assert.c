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

#include "elasticapm_assert.h"
#include <stdarg.h>
#include "log.h"
#include "ConfigManager.h"

#if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

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
void elasticApmAssertFailed(
        const char* condExpr,
        const char* filePath,
        unsigned int lineNumber,
        const char* funcName,
        const char* msg )
{
    if ( msg == NULL )
    {
        logWithLogger(
                getGlobalLogger(),
                /* isForced: */ true,
                logLevel_critical,
                makeStringViewFromString( filePath ),
                lineNumber,
                makeStringViewFromString( funcName ),
                "Assertion failed! Condition: %s.",
                condExpr );
    }
    else
    {
        logWithLogger(
                getGlobalLogger(),
                /* isForced: */ true,
                logLevel_critical,
                makeStringViewFromString( filePath ),
                lineNumber,
                makeStringViewFromString( funcName ),
                "Assertion failed! Condition: %s. Message: %s.",
                condExpr, msg );
    }

    elasticApmAbort();
}

AssertLevel internalChecksToAssertLevel( InternalChecksLevel internalChecksLevel )
{
    ELASTICAPM_STATIC_ASSERT( assertLevel_not_set == internalChecksLevel_not_set );
    ELASTICAPM_STATIC_ASSERT( numberOfAssertLevels <= numberOfInternalChecksLevels );

    ELASTICAPM_ASSERT( ELASTICAPM_IS_IN_INCLUSIVE_RANGE( internalChecksLevel_not_set, internalChecksLevel, internalChecksLevel_all ) );

    if ( internalChecksLevel >= internalChecksLevel_all ) return assertLevel_all;
    if ( internalChecksLevel < ( assertLevel_all - 1 ) ) return (AssertLevel)internalChecksLevel;
    return (AssertLevel)( assertLevel_all - 1 );
}

String streamAssertLevel( AssertLevel level, TextOutputStream* txtOutStream )
{
    if ( level == assertLevel_not_set )
        return streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfAssertLevels )
        return streamInt( level, txtOutStream );

    return streamString( assertLevelNames[ level ], txtOutStream );
}

#endif // #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
