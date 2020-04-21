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
#ifdef ELASTICAPM_MOCK_PHP_DEPS
#   include "mock_php.h"
#else
#   include <php.h>
#endif
#include "elasticapm_assert.h"
#include "ResultCode.h"
#include "util.h"
#include "log.h"
#include "internal_checks.h"
#include "MemoryTracker.h"

// Steps to add new configuration option (let's assume new option name is `my_new_option'):
//      1) Add `myNewOption' field to struct ConfigSnapshot in ConfigManager.h.
//
//      2) Add to the list of ELASTICAPM_CFG_OPT_NAME_XYZ-s in ConfigManager.h:
//          #define ELASTICAPM_CFG_OPT_NAME_MY_NEW_OPTION "my_new_option"
//
//      3) Add to PHP_INI_BEGIN() section in elasticapm.c:
//          ELASTICAPM_INI_ENTRY( ELASTICAPM_CFG_OPT_NAME_MY_NEW_OPTION )
//
//      4) Add `optionId_myNewOption' to enum OptionId in ConfigManager.h
//
//      5) Add to section of ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS in ConfigManager.c:
//          ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( <!!! myNewOption Type !!!>Value, myNewOption )
//
//      6) Add to section of ELASTICAPM_INIT_METADATA in initOptionsMetadata() in ConfigManager.c:
//          ELASTICAPM_INIT_METADATA( build<!!! myNewOption Type !!!>OptionMetadata, myNewOption, ELASTICAPM_MY_NEW_OPTION_OPTION_NAME, /* defaultValue: */ <!!! myNewOption default value !!!> );

enum OptionId
{
    #ifdef PHP_WIN32
    optionId_allowAbortDialog,
    #endif
    optionId_abortOnMemoryLeak,
    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    optionId_assertLevel,
    #endif
    optionId_enabled,
    optionId_internalChecksLevel,
    optionId_logFile,
    optionId_logLevel,
    optionId_logLevelFile,
    optionId_logLevelStderr,
    #ifndef PHP_WIN32
    optionId_logLevelSyslog,
    #endif
    #ifdef PHP_WIN32
    optionId_logLevelWinSysDebug,
    #endif
    #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    optionId_memoryTrackingLevel,
    #endif
    optionId_secretToken,
    optionId_serverUrl,
    optionId_serviceName,

    numberOfOptions
};
typedef enum OptionId OptionId;

#define ELASTICAPM_FOR_EACH_OPTION_ID( optIdVar ) ELASTICAPM_FOR_EACH_INDEX_EX( OptionId, optIdVar, numberOfOptions )

struct ConfigSnapshot
{
    bool abortOnMemoryLeak;
        #ifdef PHP_WIN32
    bool allowAbortDialog;
        #endif
        #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    AssertLevel assertLevel;
        #endif
    bool enabled;
    InternalChecksLevel internalChecksLevel;
    String logFile;
    LogLevel logLevel;
    LogLevel logLevelFile;
    LogLevel logLevelStderr;
        #ifndef PHP_WIN32
    LogLevel logLevelSyslog;
        #endif
        #ifdef PHP_WIN32
    LogLevel logLevelWinSysDebug;
        #endif
        #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTrackingLevel memoryTrackingLevel;
        #endif
    String secretToken;
    String serverUrl;
    String serviceName;
};
typedef struct ConfigSnapshot ConfigSnapshot;

struct ConfigManager;
typedef struct ConfigManager ConfigManager;

ResultCode newConfigManager( ConfigManager** pCfgManager );
const ConfigSnapshot* getConfigManagerCurrentSnapshot( const ConfigManager* cfgManager );
ResultCode ensureConfigManagerHasLatestConfig( ConfigManager* cfgManager, bool* didConfigChange );
void deleteConfigManagerAndSetToNull( ConfigManager** pCfgManager );

ResultCode getConfigManagerOptionValueByName(
        const ConfigManager* cfgManager,
        String optionName,
        zval* parsedValueAsZval,
        TextOutputStream* txtOutStream,
        String* streamedParsedValue );
void getConfigManagerOptionMetadata(
        const ConfigManager* cfgManager,
        OptionId optId,
        /* out */ bool* isSecret,
        /* out */ String* optName,
        /* out */ String* envVarName,
        /* out */ StringView* iniName );
void getConfigManagerOptionValueById(
        const ConfigManager* cfgManager,
        OptionId optId,
        TextOutputStream* txtOutStream,
        /* out */ String* streamedParsedValue,
        /* out */ String* rawValue,
        /* out */ String* rawValueSource );

enum RawConfigSource
{
    // In order of precedence

    rawConfigSource_iniFile,
    rawConfigSource_envVars,
    numberOfRawConfigSources
};
typedef enum RawConfigSource RawConfigSource;

String readRawOptionValueFromEnvVars(
        const ConfigManager* cfgManager,
        OptionId optId );
String readRawOptionValueFromIni(
        const ConfigManager* cfgManager,
        OptionId optId,
        bool* exists );
void getConfigManagerRawData(
        const ConfigManager* cfgManager,
        OptionId optId,
        RawConfigSource rawConfigSource,
        /* out */ String* originalRawValue,
        /* out */ String* interpretedRawValue );

const ConfigSnapshot* getGlobalCurrentConfigSnapshot();

#define ELASTICAPM_CFG_OPT_HAS_NO_VALUE "no value"

#define ELASTICAPM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK "abort_on_memory_leak"
#   ifdef PHP_WIN32
#define ELASTICAPM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG "allow_abort_dialog"
#   endif
#   if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
#define ELASTICAPM_CFG_OPT_NAME_ASSERT_LEVEL "assert_level"
#   endif
#define ELASTICAPM_CFG_OPT_NAME_ENABLED "enabled"
#define ELASTICAPM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL "internal_checks_level"
#define ELASTICAPM_CFG_OPT_NAME_LOG_FILE "log_file"
#define ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL "log_level"
#define ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_FILE "log_level_file"
#define ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_STDERR "log_level_stderr"
#   ifndef PHP_WIN32
#define ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG "log_level_syslog"
#   endif
#   ifdef PHP_WIN32
#define ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG "log_level_win_sys_debug"
#   endif
#   if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
#define ELASTICAPM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL "memory_tracking_level"
#   endif
#define ELASTICAPM_CFG_OPT_NAME_SECRET_TOKEN "secret_token"
#define ELASTICAPM_CFG_OPT_NAME_SERVER_URL "server_url"
#define ELASTICAPM_CFG_OPT_NAME_SERVICE_NAME "service_name"

#define ELASTICAPM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optNameStringLiteral ) ( "elasticapm." optNameStringLiteral )
