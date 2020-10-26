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
#ifdef ELASTIC_APM_MOCK_PHP_DEPS
#   include "mock_php.h"
#else
#   include <php.h>
#endif
#include "elastic_apm_assert.h"
#include "StringView.h"
#include "ResultCode.h"
#include "util.h"
#include "log.h"
#include "internal_checks.h"
#include "MemoryTracker.h"
#include "time_util.h"

// Steps to add new configuration option (let's assume new option name is `my_new_option'):
//      1) Add `myNewOption' field to struct ConfigSnapshot in ConfigManager.h.
//
//      2) Add to the list of ELASTIC_APM_CFG_OPT_NAME_XYZ-s in ConfigManager.h:
//          #define ELASTIC_APM_CFG_OPT_NAME_MY_NEW_OPTION "my_new_option"
//
//      3) Add to PHP_INI_BEGIN() section in elastic_apm.c:
//          ELASTIC_APM_INI_ENTRY( ELASTIC_APM_CFG_OPT_NAME_MY_NEW_OPTION )
//
//      4) Add `optionId_myNewOption' to enum OptionId in ConfigManager.h
//
//      5) Add to section of ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS in ConfigManager.c:
//          ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( <!!! myNewOption Type !!!>Value, myNewOption )
//
//      6) Add to section of ELASTIC_APM_INIT_METADATA in initOptionsMetadata() in ConfigManager.c:
//          ELASTIC_APM_INIT_METADATA( build<!!! myNewOption Type !!!>OptionMetadata, myNewOption, ELASTIC_APM_MY_NEW_OPTION_OPTION_NAME, /* defaultValue: */ <!!! myNewOption default value !!!> );

enum OptionId
{
    #ifdef PHP_WIN32
    optionId_allowAbortDialog,
    #endif
    optionId_abortOnMemoryLeak,
    optionId_apiKey,
    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    optionId_assertLevel,
    #endif
    optionId_bootstrapPhpPartFile,
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
    #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    optionId_memoryTrackingLevel,
    #endif
    optionId_secretToken,
    optionId_serverUrl,
    optionId_serviceName,
    optionId_verifyServerCert,

    numberOfOptions
};
typedef enum OptionId OptionId;

#define ELASTIC_APM_FOR_EACH_OPTION_ID( optIdVar ) ELASTIC_APM_FOR_EACH_INDEX_EX( OptionId, optIdVar, numberOfOptions )

struct ConfigSnapshot
{
    bool abortOnMemoryLeak;
        #ifdef PHP_WIN32
    bool allowAbortDialog;
        #endif
        #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    AssertLevel assertLevel;
        #endif
    String apiKey;
    String bootstrapPhpPartFile;
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
        #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    MemoryTrackingLevel memoryTrackingLevel;
        #endif
    String secretToken;
    String serverUrl;
    String serviceName;
    bool verifyServerCert;
};
typedef struct ConfigSnapshot ConfigSnapshot;

struct ConfigManager;
typedef struct ConfigManager ConfigManager;

ResultCode newConfigManager( ConfigManager** pCfgManager );
const ConfigSnapshot* getConfigManagerCurrentSnapshot( const ConfigManager* cfgManager );
ResultCode ensureConfigManagerHasLatestConfig( ConfigManager* cfgManager, bool* didConfigChange );
void deleteConfigManagerAndSetToNull( ConfigManager** pCfgManager );

struct GetConfigManagerOptionValueByNameResult
{
    zval parsedValueAsZval;
    TextOutputStream txtOutStream;
    String streamedParsedValue;
};
typedef struct GetConfigManagerOptionValueByNameResult GetConfigManagerOptionValueByNameResult;

ResultCode getConfigManagerOptionValueByName(
        const ConfigManager* cfgManager
        , String optionName
        , GetConfigManagerOptionValueByNameResult* result
);

struct GetConfigManagerOptionMetadataResult
{
    bool isSecret;
    String optName;
    String envVarName;
    StringView iniName;
};
typedef struct GetConfigManagerOptionMetadataResult GetConfigManagerOptionMetadataResult;

void getConfigManagerOptionMetadata(
        const ConfigManager* cfgManager
        , OptionId optId
        , GetConfigManagerOptionMetadataResult* result
);

struct GetConfigManagerOptionValueByIdResult
{
    TextOutputStream txtOutStream;
    String streamedParsedValue;
    String rawValue;
    String rawValueSourceDescription;
};
typedef struct GetConfigManagerOptionValueByIdResult GetConfigManagerOptionValueByIdResult;

void getConfigManagerOptionValueById(
        const ConfigManager* cfgManager
        , OptionId optId
        , GetConfigManagerOptionValueByIdResult* result
);

enum RawConfigSourceId
{
    // In order of precedence

    rawConfigSourceId_iniFile,
    rawConfigSourceId_envVars,
    numberOfRawConfigSources
};
typedef enum RawConfigSourceId RawConfigSourceId;

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
        RawConfigSourceId rawCfgSourceId,
        /* out */ String* originalRawValue,
        /* out */ String* interpretedRawValue );

const ConfigSnapshot* getGlobalCurrentConfigSnapshot();

#define ELASTIC_APM_CFG_OPT_HAS_NO_VALUE "no value"

#define ELASTIC_APM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK "abort_on_memory_leak"
#   ifdef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG "allow_abort_dialog"
#   endif
#define ELASTIC_APM_CFG_OPT_NAME_API_KEY "api_key"
#   if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
#define ELASTIC_APM_CFG_OPT_NAME_ASSERT_LEVEL "assert_level"
#   endif
#define ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE "bootstrap_php_part_file"
#define ELASTIC_APM_CFG_OPT_NAME_ENABLED "enabled"
#define ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT "environment"
#define ELASTIC_APM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL "internal_checks_level"
#define ELASTIC_APM_CFG_OPT_NAME_LOG_FILE "log_file"
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL "log_level"
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE "log_level_file"
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR "log_level_stderr"
#   ifndef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG "log_level_syslog"
#   endif
#   ifdef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG "log_level_win_sys_debug"
#   endif
#   if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
#define ELASTIC_APM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL "memory_tracking_level"
#   endif
#define ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN "secret_token"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT "server_timeout"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_URL "server_url"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME "service_name"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION "service_version"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE "transaction_sample_rate"
#define ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT "verify_server_cert"

#define ELASTIC_APM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optNameStringLiteral ) ( "elastic_apm." optNameStringLiteral )
