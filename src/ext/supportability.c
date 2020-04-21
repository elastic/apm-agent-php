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

#include "supportability.h"
#include <php.h>
#include <ext/standard/info.h>
#include <SAPI.h>
#include "php_elasticapm.h"
#include "log.h"
#include "ConfigManager.h"
#include "util_for_php_types.h"
#include "elasticapm_assert.h"
#include "MemoryTracker.h"

static const String redacted = "***";

static
String redactIfSecret( String value, bool isSecret )
{
    return value == NULL ? NULL : ( isSecret ? redacted : value );
}

static
void printSectionHeading( String heading )
{
    php_info_print_table_start();
    php_info_print_table_header( 1, heading );
    php_info_print_table_end();
}

static
void printConfigurationInfo()
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    printSectionHeading( "Configuration" );
    php_info_print_table_start();
    php_info_print_table_header( 4, "Option", "Parsed value", "Raw value", "Source" );
    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        GetConfigManagerOptionValueByIdResult getValRes;
        char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        getValRes.txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        getValRes.txtOutStream.shouldEncloseUserString = ( sapi_module.phpinfo_as_text != 0 );

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerOptionValueById( cfgManager, optId, &getValRes );
        php_info_print_table_row(
                4,
                getMetaRes.optName,
                redactIfSecret( getValRes.rawValue == NULL ? NULL : getValRes.streamedParsedValue, getMetaRes.isSecret ),
                redactIfSecret( getValRes.rawValue, getMetaRes.isSecret ),
                getValRes.rawValueSourceDescription == NULL ? "Default" : getValRes.rawValueSourceDescription );
    }
    php_info_print_table_end();
}

static
void printIniEntries()
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    printSectionHeading( "INI entries" );
    php_info_print_table_start();
    php_info_print_table_header(
            4,
            "Name",
            "Raw value used for the current config",
            "Interpreted raw value used for the current config",
            "Current value" );
    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        String originalRawValue = NULL;
        String interpretedRawValue = NULL;

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerRawData(
                cfgManager,
                optId,
                rawConfigSource_iniFile,
                /* out */ &originalRawValue,
                /* out */ &interpretedRawValue );

        char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        bool currentValueExists;
        php_info_print_table_row(
                4,
                streamStringView( getMetaRes.iniName, &txtOutStream ),
                originalRawValue,
                interpretedRawValue,
                redactIfSecret( readRawOptionValueFromIni( cfgManager, optId, &currentValueExists ), getMetaRes.isSecret ) );
    }
    php_info_print_table_end();
}

static
void printEnvVars()
{
    const ConfigManager* const cfgManager = getGlobalTracer()->configManager;
    printSectionHeading( "Environment variables" );
    php_info_print_table_start();
    php_info_print_table_header( 3, "Name", "Value used for the current config", "Current value" );
    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        GetConfigManagerOptionMetadataResult getMetaRes;
        String originalRawValue = NULL;
        String interpretedRawValue = NULL;

        getConfigManagerOptionMetadata( cfgManager, optId, &getMetaRes );
        getConfigManagerRawData(
                cfgManager,
                optId,
                rawConfigSource_envVars,
                /* out */ &originalRawValue,
                /* out */ &interpretedRawValue );

        php_info_print_table_row(
                3,
                getMetaRes.envVarName,
                originalRawValue,
                redactIfSecret( readRawOptionValueFromEnvVars( cfgManager, optId ), getMetaRes.isSecret ) );
    }
    php_info_print_table_end();
}

static
void printMiscSelfDiagnostics()
{
    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    php_info_print_table_start();

    php_info_print_table_header( 3, "What", "Current", "Default" );

    php_info_print_table_row(
            3,
            "Assert level",
            streamAssertLevel( getGlobalAssertLevel(), &txtOutStream ),
            streamAssertLevel( ELASTICAPM_ASSERT_DEFAULT_LEVEL, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    php_info_print_table_row(
            3,
            "Memory tracking level",
            streamMemoryTrackingLevel( getGlobalMemoryTracker()->level, &txtOutStream ),
            streamMemoryTrackingLevel( ELASTICAPM_MEMORY_TRACKING_DEFAULT_LEVEL, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );
    php_info_print_table_row(
            3,
            "Abort on memory leak",
            boolToString( getGlobalMemoryTracker()->abortOnMemoryLeak ),
            boolToString( ELASTICAPM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK ) );
    textOutputStreamRewind( &txtOutStream );

    php_info_print_table_row(
            3,
            "Internal checks level",
            streamInternalChecksLevel( getGlobalInternalChecksLevel(), &txtOutStream ),
            streamInternalChecksLevel( ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    php_info_print_table_row( 2, "NDEBUG defined",
    #ifdef NDEBUG
            "Yes"
    #else
            "No"
    #endif
    );
    php_info_print_table_row( 2, "ELASTICAPM_IS_DEBUG_BUILD_01",
            streamInt( (int)(ELASTICAPM_IS_DEBUG_BUILD_01), &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    php_info_print_table_end();
}

static
void printEffectiveLogLevels()
{
    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    const Logger* const logger = &( getGlobalTracer()->logger );

    printSectionHeading( "Effective log levels" );
    php_info_print_table_start();
    php_info_print_table_header( 3, "Sink", "Current", "Default" );

    ELASTICAPM_FOR_EACH_LOG_SINK_TYPE( logSinkType )
    {
        php_info_print_table_row(
                3,
                logSinkTypeName[ logSinkType ],
                streamLogLevel( logger->config.levelPerSinkType[ logSinkType ], &txtOutStream ),
                streamLogLevel( defaultLogLevelPerSinkType[ logSinkType ], &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
    }

    php_info_print_table_row(
            3,
            "Max enabled log level",
            streamLogLevel( logger->maxEnabledLevel, &txtOutStream ),
            streamLogLevel( calcMaxEnabledLogLevel( defaultLogLevelPerSinkType ), &txtOutStream ) );

    php_info_print_table_end();
}

void elasticapmModuleInfo( zend_module_entry* zend_module )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    php_info_print_table_start();
    php_info_print_table_row( 2, "Version", PHP_ELASTICAPM_VERSION );
    php_info_print_table_end();

    printConfigurationInfo();

    printIniEntries();
    printEnvVars();

    printSectionHeading( "Self Diagnostics" );

    printMiscSelfDiagnostics();
    printEffectiveLogLevels();

    printSectionHeading( "INI entries (displayed by default PHP mechanism)" );
    DISPLAY_INI_ENTRIES();

    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();
}

static
const zend_string* iniEntryValue( zend_ini_entry* iniEntry, int type )
{
    return ( type == ZEND_INI_DISPLAY_ORIG ) ? ( iniEntry->modified ? iniEntry->orig_value : iniEntry->value ) : iniEntry->value;
}

void displaySecretIniValue( zend_ini_entry* iniEntry, int type )
{
    const String noValue = ELASTICAPM_CFG_OPT_HAS_NO_VALUE;
    const String valueToPrint = isNullOrEmtpyZstring( iniEntryValue( iniEntry, type ) ) ? noValue : redacted;

    php_printf( sapi_module.phpinfo_as_text ? "%s" : "<i>%s</i>", valueToPrint );
}
