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

#include "supportability.h"
#include "supportability_zend.h"
#include <php.h>
#include <ext/standard/info.h>
#include <SAPI.h>
#include "php_elastic_apm.h"
#include "log.h"
#include "ConfigManager.h"
#include "util_for_PHP.h"
#include "elastic_apm_assert.h"
#include "MemoryTracker.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_SUPPORT

static const String redacted = "***";

static
String redactIfSecret( String value, bool isSecret )
{
    return value == NULL ? NULL : ( isSecret ? redacted : value );
}

void php_info_printSectionHeading( StructuredTextPrinter* structTxtPrinter, String heading )
{
    ELASTIC_APM_UNUSED( structTxtPrinter );

    php_info_print_table_start();
    php_info_print_table_header( 1, heading );
    php_info_print_table_end();
}

void php_info_printTableBegin( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns )
{
    ELASTIC_APM_UNUSED( structTxtPrinter );
    ELASTIC_APM_UNUSED( numberOfColumns );

    php_info_print_table_start();
}

static
void php_info_printTableCells(
        StructuredTextPrinter* structTxtPrinter
        , size_t numberOfColumns
        , String columns[]
        , void (* variadicPrintCellsFunc )( int numberOfColumns, ... )
)
{
    ELASTIC_APM_UNUSED( structTxtPrinter );

    switch ( numberOfColumns )
    {
        case 0:
            variadicPrintCellsFunc( 0 );
            return;

        case 1:
            variadicPrintCellsFunc( 1, columns[ 0 ] );
            return;

        case 2:
            variadicPrintCellsFunc( 2, columns[ 0 ], columns[ 1 ] );
            return;

        case 3:
            variadicPrintCellsFunc( 3, columns[ 0 ], columns[ 1 ], columns[ 2 ] );
            return;

        case 4:
            variadicPrintCellsFunc( 4, columns[ 0 ], columns[ 1 ], columns[ 2 ], columns[ 3 ] );
            return;

        default:
            variadicPrintCellsFunc( 5, columns[ 0 ], columns[ 1 ], columns[ 2 ], columns[ 3 ], columns[ 4 ] );
            return;
    }
}

void php_info_printTableHeader( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columnHeaders[] )
{
    php_info_printTableCells(
            structTxtPrinter
            , numberOfColumns
            , columnHeaders
            , php_info_print_table_header );
}

void php_info_printTableRow( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columns[] )
{
    ELASTIC_APM_UNUSED( structTxtPrinter );

    php_info_printTableCells(
            structTxtPrinter
            , numberOfColumns
            , columns
            , php_info_print_table_row );
}

void php_info_printTableEnd( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns )
{
    ELASTIC_APM_UNUSED( structTxtPrinter );
    ELASTIC_APM_UNUSED( numberOfColumns );

    php_info_print_table_end();
}

void init_php_info_StructuredTextPrinter( StructuredTextPrinter* structTxtPrinter )
{
    structTxtPrinter->printSectionHeading = php_info_printSectionHeading;
    structTxtPrinter->printTableBegin = php_info_printTableBegin;
    structTxtPrinter->printTableHeader = php_info_printTableHeader;
    structTxtPrinter->printTableRow = php_info_printTableRow;
    structTxtPrinter->printTableEnd = php_info_printTableEnd;
}

static
void printConfigurationInfo( StructuredTextPrinter* structTxtPrinter )
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    structTxtPrinter->printSectionHeading( structTxtPrinter, "Configuration" );
    String columnHeaders[] = { "Option", "Parsed value", "Raw value", "Source" };
    enum { numberOfColumns = ELASTIC_APM_STATIC_ARRAY_SIZE( columnHeaders ) };
    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    structTxtPrinter->printTableHeader( structTxtPrinter, numberOfColumns, columnHeaders );
    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        GetConfigManagerOptionValueByIdResult getValRes;
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        getValRes.txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        getValRes.txtOutStream.shouldEncloseUserString = ( sapi_module.phpinfo_as_text != 0 );

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerOptionValueById( cfgManager, optId, &getValRes );
        String columns[ numberOfColumns ] =
                {
                        getMetaRes.optName
                        , redactIfSecret( getValRes.streamedParsedValue, getMetaRes.isSecret )
                        , redactIfSecret( getValRes.rawValue, getMetaRes.isSecret )
                        , getValRes.rawValueSourceDescription == NULL ? "Default" : getValRes.rawValueSourceDescription
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }
    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

static
void printIniEntries( StructuredTextPrinter* structTxtPrinter )
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    structTxtPrinter->printSectionHeading( structTxtPrinter, "INI entries" );
    String columnHeaders[] =
            {
                    "Name"
                    , "Raw value used for the current config"
                    , "Interpreted raw value used for the current config"
                    , "Current value"
            };
    enum { numberOfColumns = ELASTIC_APM_STATIC_ARRAY_SIZE( columnHeaders ) };
    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    structTxtPrinter->printTableHeader( structTxtPrinter, numberOfColumns, columnHeaders );
    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        String originalRawValue = NULL;
        String interpretedRawValue = NULL;

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerRawData(
                cfgManager,
                optId,
                rawConfigSourceId_iniFile,
                /* out */ &originalRawValue,
                /* out */ &interpretedRawValue );

        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        bool currentValueExists;
        String columns[ numberOfColumns ] =
                {
                        streamStringView( getMetaRes.iniName, &txtOutStream )
                        , redactIfSecret( originalRawValue, getMetaRes.isSecret )
                        , redactIfSecret( interpretedRawValue, getMetaRes.isSecret )
                        , redactIfSecret( readRawOptionValueFromIni( cfgManager, optId, &currentValueExists ), getMetaRes.isSecret )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }
    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

static
void printEnvVars( StructuredTextPrinter* structTxtPrinter )
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    structTxtPrinter->printSectionHeading( structTxtPrinter, "Environment variables" );

    String columnHeaders[] = { "Name", "Value used for the current config", "Current value" };
    enum { numberOfColumns = ELASTIC_APM_STATIC_ARRAY_SIZE( columnHeaders ) };

    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    structTxtPrinter->printTableHeader( structTxtPrinter, numberOfColumns, columnHeaders );
    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        String originalRawValue = NULL;
        String interpretedRawValue = NULL;

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerRawData(
                cfgManager,
                optId,
                rawConfigSourceId_envVars,
                /* out */ &originalRawValue,
                /* out */ &interpretedRawValue );

        String columns[ numberOfColumns ] =
                {
                        getMetaRes.envVarName
                        , redactIfSecret( originalRawValue, getMetaRes.isSecret )
                        , redactIfSecret( readRawOptionValueFromEnvVars( cfgManager, optId ), getMetaRes.isSecret )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }
    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

static
void printMiscSelfDiagnostics( StructuredTextPrinter* structTxtPrinter )
{
    structTxtPrinter->printSectionHeading( structTxtPrinter, "Misc. self diagnostics" );

    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String columnHeaders[] = { "What", "Current", "Default" };
    enum { numberOfColumns = ELASTIC_APM_STATIC_ARRAY_SIZE( columnHeaders ) };

    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    structTxtPrinter->printTableHeader( structTxtPrinter, numberOfColumns, columnHeaders );

    {
        String columns[ numberOfColumns ] =
                {
                        "Assert level"
                        , streamAssertLevel( getGlobalAssertLevel(), &txtOutStream )
                        , streamAssertLevel( ELASTIC_APM_ASSERT_DEFAULT_LEVEL, &txtOutStream )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    {
        textOutputStreamRewind( &txtOutStream );
        String columns[ numberOfColumns ] =
                {
                        "Memory tracking level"
                        , streamMemoryTrackingLevel( getGlobalMemoryTracker()->level, &txtOutStream )
                        , streamMemoryTrackingLevel( ELASTIC_APM_MEMORY_TRACKING_DEFAULT_LEVEL, &txtOutStream )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    {
        textOutputStreamRewind( &txtOutStream );
        String columns[ numberOfColumns ] =
                {
                        "Abort on memory leak"
                        , boolToString( getGlobalMemoryTracker()->abortOnMemoryLeak )
                        , boolToString( ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    {
        textOutputStreamRewind( &txtOutStream );
        String columns[ numberOfColumns ] =
                {
                        "Internal checks level"
                        , streamInternalChecksLevel( getGlobalInternalChecksLevel(), &txtOutStream )
                        , streamInternalChecksLevel( ELASTIC_APM_INTERNAL_CHECKS_DEFAULT_LEVEL, &txtOutStream )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    {
        textOutputStreamRewind( &txtOutStream );
        String columns[ numberOfColumns - 1 ] =
                {
                        "NDEBUG defined"
                        #ifdef NDEBUG
                        , "Yes"
                        #else
                        , "No"
                        #endif
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    {
        textOutputStreamRewind( &txtOutStream );
        String columns[ numberOfColumns - 1 ] =
                {
                        "ELASTIC_APM_IS_DEBUG_BUILD_01"
                        , streamInt( (int)(ELASTIC_APM_IS_DEBUG_BUILD_01), &txtOutStream )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    }

    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

static
void printEffectiveLogLevels( StructuredTextPrinter* structTxtPrinter )
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    const Logger* const logger = &( getGlobalTracer()->logger );

    structTxtPrinter->printSectionHeading( structTxtPrinter, "Effective log levels" );

    String columnHeaders[] = { "Sink", "Current", "Default" };
    enum { numberOfColumns = ELASTIC_APM_STATIC_ARRAY_SIZE( columnHeaders ) };

    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    structTxtPrinter->printTableHeader( structTxtPrinter, numberOfColumns, columnHeaders );

    ELASTIC_APM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
    {
        String columns[ numberOfColumns ] =
                {
                        logSinkTypeName[ logSinkType ]
                        , streamLogLevel( logger->config.levelPerSinkType[ logSinkType ], &txtOutStream )
                        , streamLogLevel( defaultLogLevelPerSinkType[ logSinkType ], &txtOutStream )
                };
        structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
        textOutputStreamRewind( &txtOutStream );
    }

    String columns[ numberOfColumns ] =
            {
                    "Max enabled log level"
                    , streamLogLevel( logger->maxEnabledLevel, &txtOutStream )
                    , streamLogLevel( calcMaxEnabledLogLevel( defaultLogLevelPerSinkType ), &txtOutStream )
            };
    structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );

    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

static
void printMiscInfo( StructuredTextPrinter* structTxtPrinter )
{
    structTxtPrinter->printSectionHeading( structTxtPrinter, "Misc. info" );

    enum { numberOfColumns = 2 };
    structTxtPrinter->printTableBegin( structTxtPrinter, numberOfColumns );
    String columns[numberOfColumns] = { "Version", PHP_ELASTIC_APM_VERSION };
    structTxtPrinter->printTableRow( structTxtPrinter, ELASTIC_APM_STATIC_ARRAY_SIZE( columns ), columns );
    structTxtPrinter->printTableEnd( structTxtPrinter, numberOfColumns );
}

void printSupportabilityInfo( StructuredTextPrinter* structTxtPrinter )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    printMiscInfo( structTxtPrinter );

    printConfigurationInfo( structTxtPrinter );

    printIniEntries( structTxtPrinter );
    printEnvVars( structTxtPrinter );

    printMiscSelfDiagnostics( structTxtPrinter );
    printEffectiveLogLevels( structTxtPrinter );

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT();
}

void elasticApmModuleInfo( zend_module_entry* zend_module )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();

    StructuredTextPrinter structTxtPrinter;
    init_php_info_StructuredTextPrinter( &structTxtPrinter );

    printSupportabilityInfo( &structTxtPrinter );

    structTxtPrinter.printSectionHeading( &structTxtPrinter, "INI entries (displayed by default PHP mechanism)" );
    DISPLAY_INI_ENTRIES();

    ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT();
}

static
const zend_string* iniEntryValue( zend_ini_entry* iniEntry, int type )
{
    return ( type == ZEND_INI_DISPLAY_ORIG ) ? ( iniEntry->modified ? iniEntry->orig_value : iniEntry->value ) : iniEntry->value;
}

void displaySecretIniValue( zend_ini_entry* iniEntry, int type )
{
    const String noValue = ELASTIC_APM_CFG_OPT_HAS_NO_VALUE;
    const String valueToPrint = isNullOrEmtpyZstring( iniEntryValue( iniEntry, type ) ) ? noValue : redacted;

    php_printf( sapi_module.phpinfo_as_text ? "%s" : "<i>%s</i>", valueToPrint );
}

void printSectionHeadingToTextOutputStream( StructuredTextPrinter* structTxtPrinter, String heading )
{
    StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter = (StructuredTextToOutputStreamPrinter*)structTxtPrinter;

    streamStringView( structTxtToOutStreamPrinter->prefix, structTxtToOutStreamPrinter->txtOutStream );
    streamPrintf( structTxtToOutStreamPrinter->txtOutStream, "\n" );
    streamStringView( structTxtToOutStreamPrinter->prefix, structTxtToOutStreamPrinter->txtOutStream );
    streamPrintf( structTxtToOutStreamPrinter->txtOutStream, "%s\n", heading );
}

#define ELASTIC_APM_MIN_TABLE_CELL_MIN_WIDTH 25

// Section A
//      +---------------------------------+---------------------------------+---------------------------------+
//      | Header 1                        | Header 2                        | Header 3                        |
//      +---------------------------------+---------------------------------+---------------------------------+
//      | 1234567890123456789001234567890 | 1234567890123456789001234567890 | abc                             |
//      | 456                             | abc                             | 1234567890123456789001234567890 |
//      +---------------------------------+---------------------------------+---------------------------------+
//
// Section B
//      +---------------------------------+---------------------------------+---------------------------------+
//      | Header 1                        | Header 2                        | Header 3                        |
//      +---------------------------------+---------------------------------+---------------------------------+
//      | 1234567890123456789001234567890 | 1234567890123456789001234567890 | abc                             |
//      | 456                             | abc                             | 1234567890123456789001234567890 |
//      +---------------------------------+---------------------------------+---------------------------------+

static
void beginTableLineToTextOutputStream( StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter )
{
    streamStringView( structTxtToOutStreamPrinter->prefix, structTxtToOutStreamPrinter->txtOutStream );
    streamIndent( /* nestingDepth */ 1, structTxtToOutStreamPrinter->txtOutStream );
}

void printTableHorizontalBorder( size_t numberOfColumns, StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter )
{
    if ( numberOfColumns == 0 ) return;

    beginTableLineToTextOutputStream( structTxtToOutStreamPrinter );

    streamChar( '+', structTxtToOutStreamPrinter->txtOutStream );
    ELASTIC_APM_REPEAT_N_TIMES( numberOfColumns )
    {
        ELASTIC_APM_REPEAT_N_TIMES( ELASTIC_APM_MIN_TABLE_CELL_MIN_WIDTH + 2 )
        {
            streamChar( '-', structTxtToOutStreamPrinter->txtOutStream );
        }
        streamChar( '+', structTxtToOutStreamPrinter->txtOutStream );
    }
    streamPrintf( structTxtToOutStreamPrinter->txtOutStream, "\n" );
}

void printTableBeginToTextOutputStream( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns )
{
    printTableHorizontalBorder( numberOfColumns, (StructuredTextToOutputStreamPrinter*)structTxtPrinter );
}

static
void printTableRowToTextOutputStream( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columns[] )
{
    if ( numberOfColumns == 0 ) return;

    StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter = (StructuredTextToOutputStreamPrinter*)structTxtPrinter;

    beginTableLineToTextOutputStream( structTxtToOutStreamPrinter );
    streamString( "|", structTxtToOutStreamPrinter->txtOutStream );
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfColumns )
    {
        streamString( " ", structTxtToOutStreamPrinter->txtOutStream );
        streamPrintf( structTxtToOutStreamPrinter->txtOutStream
                      , "%-" ELASTIC_APM_PP_STRINGIZE( ELASTIC_APM_MIN_TABLE_CELL_MIN_WIDTH ) "s"
                      , columns[ i ] );
        streamString( " |", structTxtToOutStreamPrinter->txtOutStream );
    }
    streamPrintf( structTxtToOutStreamPrinter->txtOutStream, "\n" );
}

static
void printTableHeaderToTextOutputStream( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columnHeaders[] )
{
    if ( numberOfColumns == 0 ) return;

    StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter = (StructuredTextToOutputStreamPrinter*)structTxtPrinter;

    printTableRowToTextOutputStream( structTxtPrinter, numberOfColumns, columnHeaders );

    printTableHorizontalBorder( numberOfColumns, structTxtToOutStreamPrinter );
}

void printTableEndToTextOutputStream( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns )
{
    printTableHorizontalBorder( numberOfColumns, (StructuredTextToOutputStreamPrinter*)structTxtPrinter );
}
#undef ELASTIC_APM_MIN_TABLE_CELL_MIN_WIDTH

void initStructuredTextToOutputStreamPrinter(
        /* in */ TextOutputStream* txtOutStream
        , StringView prefix
        , /* out */ StructuredTextToOutputStreamPrinter* structTxtToOutStreamPrinter
)
{
    structTxtToOutStreamPrinter->base.printSectionHeading = printSectionHeadingToTextOutputStream;
    structTxtToOutStreamPrinter->base.printTableBegin = printTableBeginToTextOutputStream;
    structTxtToOutStreamPrinter->base.printTableHeader = printTableHeaderToTextOutputStream;
    structTxtToOutStreamPrinter->base.printTableRow = printTableRowToTextOutputStream;
    structTxtToOutStreamPrinter->base.printTableEnd = printTableEndToTextOutputStream;

    structTxtToOutStreamPrinter->txtOutStream = txtOutStream;
    structTxtToOutStreamPrinter->prefix = prefix;
}
