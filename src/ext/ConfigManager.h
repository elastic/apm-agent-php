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
//          If the option is used only in PHP part of the agent
//          then the type of field can be String
//          which will skip parsing the value by the C part of the agent.
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
//          ELASTIC_APM_INIT_METADATA( build<!!! myNewOption Type !!!>OptionMetadata, myNewOption, ELASTIC_APM_CFG_OPT_NAME_MY_NEW_OPTION, /* defaultValue: */ <!!! myNewOption default value !!!> );
//
//      7) Document the new configuration option at docs/configuration.asciidoc

struct OptionalBool
{
    bool isSet;
    bool value;
};
typedef struct OptionalBool OptionalBool;

static inline String optionalBoolToString( OptionalBool optionalBoolValue )
{
    return optionalBoolValue.isSet ? "not set" : boolToString( optionalBoolValue.value );
}

static inline OptionalBool makeNotSetOptionalBool()
{
    return (OptionalBool){ .isSet = false };
}

static inline OptionalBool makeSetOptionalBool( bool value )
{
    return (OptionalBool){ .isSet = true, .value = value };
}

enum OptionId
{
    optionId_abortOnMemoryLeak,
    #ifdef PHP_WIN32
    optionId_allowAbortDialog,
    #endif
    optionId_apiKey,
    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    optionId_assertLevel,
    #endif
    optionId_asyncBackendComm,
    optionId_bootstrapPhpPartFile,
    optionId_breakdownMetrics,
    optionId_devInternal,
    optionId_disableInstrumentations,
    optionId_disableSend,
    optionId_enabled,
    optionId_environment,
    optionId_hostname,
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
    optionId_profilingInferredSpansEnabled,
    optionId_sanitizeFieldNames,
    optionId_secretToken,
    optionId_serverTimeout,
    optionId_serverUrl,
    optionId_serviceName,
    optionId_serviceNodeName,
    optionId_serviceVersion,
    optionId_transactionIgnoreUrls,
    optionId_transactionMaxSpans,
    optionId_transactionSampleRate,
    optionId_urlGroups,
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
    OptionalBool asyncBackendComm;
    String bootstrapPhpPartFile;
    bool breakdownMetrics;
    String devInternal;
    String disableInstrumentations;
    String disableSend;
    bool enabled;
    String environment;
    String hostname;
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
    bool profilingInferredSpansEnabled;
    String sanitizeFieldNames;
    String secretToken;
    String serverUrl;
    String serverTimeout;
    String serviceName;
    String serviceNodeName;
    String serviceVersion;
    String transactionIgnoreUrls;
    String transactionMaxSpans;
    String transactionSampleRate;
    String urlGroups;
    bool verifyServerCert;
};
typedef struct ConfigSnapshot ConfigSnapshot;

struct ConfigManager;
typedef struct ConfigManager ConfigManager;

ResultCode newConfigManager( ConfigManager** pCfgManager, bool isLoggingRelatedOnly );
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
    bool isDynamic;
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
#define ELASTIC_APM_CFG_OPT_NAME_ASYNC_BACKEND_COMM "async_backend_comm"
#define ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE "bootstrap_php_part_file"
#define ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS "breakdown_metrics"
#define ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL "dev_internal"
#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS "disable_instrumentations"
#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND "disable_send"
#define ELASTIC_APM_CFG_OPT_NAME_ENABLED "enabled"
#define ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT "environment"
#define ELASTIC_APM_CFG_OPT_NAME_HOSTNAME "hostname"
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
#define ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_ENABLED "profiling_inferred_spans_enabled"
#define ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES "sanitize_field_names"
#define ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN "secret_token"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT "server_timeout"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_URL "server_url"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME "service_name"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME "service_node_name"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION "service_version"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS "transaction_ignore_urls"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS "transaction_max_spans"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE "transaction_sample_rate"
#define ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS "url_groups"
#define ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT "verify_server_cert"

#define ELASTIC_APM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optNameStringLiteral ) ( "elastic_apm." optNameStringLiteral )
