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

#include "internal_checks.h"
#include "TextOutputStream.h"

const char* internalChecksLevelNames[ numberOfInternalChecksLevels ] =
{
    [ internalChecksLevel_off ] = "OFF",
    [ internalChecksLevel_1 ] = "1",
    [ internalChecksLevel_2 ] = "2",
    [ internalChecksLevel_3 ] = "3",
    [ internalChecksLevel_all ] = "ALL"
};

String streamInternalChecksLevel( InternalChecksLevel level, TextOutputStream* txtOutStream )
{
    if ( level == internalChecksLevel_not_set )
        return streamStringView( ELASTICAPM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfInternalChecksLevels )
        return streamInt( level, txtOutStream );

    return streamString( internalChecksLevelNames[ level ], txtOutStream );
}
