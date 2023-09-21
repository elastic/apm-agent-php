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

#ifdef ELASTIC_APM_MOCK_PHP_DEPS
#   include "mock_php.h"
#else
#   include <php.h>
#endif
#include "ConfigSnapshot_forward_decl.h"
#include "elastic_apm_assert_enabled.h"
#include "StringView.h"
#include "ResultCode.h"
#include "util.h"
#include "log.h"
#include "internal_checks.h"
#include "MemoryTracker.h"
#include "time_util.h"

// Steps to add new configuration option (let's assume new option name is `my_new_option'):
//      1) Add `myNewOption' field to struct ConfigSnapshot in ConfigSnapshot.h.
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
    optionId_astProcessEnabled,
    optionId_astProcessDebugDumpConvertedBackToSource,
    optionId_astProcessDebugDumpForPathPrefix,
    optionId_astProcessDebugDumpOutDir,
    optionId_asyncBackendComm,
    optionId_bootstrapPhpPartFile,
    optionId_breakdownMetrics,
    optionId_captureErrors,
    optionId_devInternal,
    optionId_devInternalBackendCommLogVerbose,
    optionId_disableInstrumentations,
    optionId_disableSend,
    optionId_enabled,
    optionId_environment,
    optionId_globalLabels,
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
    optionId_nonKeywordStringMaxLength,
    optionId_profilingInferredSpansEnabled,
    optionId_profilingInferredSpansMinDuration,
    optionId_profilingInferredSpansSamplingInterval,
    optionId_sanitizeFieldNames,
    optionId_secretToken,
    optionId_serverTimeout,
    optionId_serverUrl,
    optionId_serviceName,
    optionId_serviceNodeName,
    optionId_serviceVersion,
    optionId_spanCompressionEnabled,
    optionId_spanCompressionExactMatchMaxDuration,
    optionId_spanCompressionSameKindMaxDuration,
    optionId_spanStackTraceMinDuration,
    optionId_stackTraceLimit,
    optionId_transactionIgnoreUrls,
    optionId_transactionMaxSpans,
    optionId_transactionSampleRate,
    optionId_urlGroups,
    optionId_verifyServerCert,
    optionId_debugDiagnosticsFile,

    numberOfOptions
};
typedef enum OptionId OptionId;

#ifdef __cplusplus
inline OptionId &operator++(OptionId &id) {
    id  = static_cast<OptionId>(static_cast<int>(id) + 1);
    return id; 
}
#endif


#define ELASTIC_APM_FOR_EACH_OPTION_ID( optIdVar ) ELASTIC_APM_FOR_EACH_INDEX_EX( OptionId, optIdVar, numberOfOptions )

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

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK "abort_on_memory_leak"

/**
 * Internal configuration option (not included in public documentation)
 */
#   ifdef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG "allow_abort_dialog"
#   endif

#define ELASTIC_APM_CFG_OPT_NAME_API_KEY "api_key"

/**
 * Internal configuration option (not included in public documentation)
 */
#   if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
#define ELASTIC_APM_CFG_OPT_NAME_ASSERT_LEVEL "assert_level"
#   endif

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_ENABLED "ast_process_enabled"

/**
 * Internal configuration options (not included in public documentation)
 * In addition to supportability this option is used by component tests as well.
 * @see tests/ElasticApmTests/ComponentTests/WordPressAutoInstrumentationTest.php
 */
#define ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE "ast_process_debug_dump_converted_back_to_source"
#define ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX "ast_process_debug_dump_for_path_prefix"
#define ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_OUT_DIR "ast_process_debug_dump_out_dir"

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_ASYNC_BACKEND_COMM "async_backend_comm"

#define ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE "bootstrap_php_part_file"
#define ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS "breakdown_metrics"

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS "capture_errors"

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL "dev_internal"
#define ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL_BACKEND_COMM_LOG_VERBOSE "dev_internal_backend_comm_log_verbose"

#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS "disable_instrumentations"
#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND "disable_send"
#define ELASTIC_APM_CFG_OPT_NAME_ENABLED "enabled"
#define ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT "environment"
#define ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS "global_labels"
#define ELASTIC_APM_CFG_OPT_NAME_HOSTNAME "hostname"

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL "internal_checks_level"

#define ELASTIC_APM_CFG_OPT_NAME_LOG_FILE "log_file"
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL "log_level"

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE "log_level_file"

#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR "log_level_stderr"
#   ifndef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG "log_level_syslog"
#   endif

/**
 * Internal configuration option (not included in public documentation)
 */
#   ifdef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG "log_level_win_sys_debug"
#   endif

/**
 * Internal configuration option (not included in public documentation)
 */
#   if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
#define ELASTIC_APM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL "memory_tracking_level"
#   endif

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH "non_keyword_string_max_length"

/**
 * Experimental configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_ENABLED "profiling_inferred_spans_enabled"
#define ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_MIN_DURATION "profiling_inferred_spans_min_duration"
#define ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL "profiling_inferred_spans_sampling_interval"

#define ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES "sanitize_field_names"
#define ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN "secret_token"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT "server_timeout"
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_URL "server_url"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME "service_name"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME "service_node_name"
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION "service_version"
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED "span_compression_enabled"
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION "span_compression_exact_match_max_duration"
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION "span_compression_same_kind_max_duration"
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION "span_stack_trace_min_duration"
#define ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT "stack_trace_limit"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS "transaction_ignore_urls"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS "transaction_max_spans"
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE "transaction_sample_rate"
#define ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS "url_groups"
#define ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT "verify_server_cert"

#define ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE "debug_diagnostic_file"


#define ELASTIC_APM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optNameStringLiteral ) ( "elastic_apm." optNameStringLiteral )
