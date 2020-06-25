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
#include <stdarg.h>
#include "ResultCode.h"
#include "basic_types.h"
#include "basic_macros.h" // ELASTIC_APM_PRINTF_ATTRIBUTE
#include "TextOutputStream.h"

/**
 * The order is important because lower numeric values are considered contained in higher ones
 * for example logLevel_error means that both logLevel_error and logLevel_critical is enabled.
 */
enum LogLevel
{
    /**
     * logLevel_not_set should not be used by logging statements - it is used only in configuration.
     */
    logLevel_not_set = -1,

    /**
     * logLevel_off should not be used by logging statements - it is used only in configuration.
     */
    logLevel_off = 0,

    logLevel_critical,
    logLevel_error,
    logLevel_warning,
    logLevel_notice,
    logLevel_info,
    logLevel_debug,
    logLevel_trace,

    numberOfLogLevels
};
typedef enum LogLevel LogLevel;

extern String logLevelNames[ numberOfLogLevels ];

enum LogSinkType
{
    logSink_stderr,

    #ifndef PHP_WIN32
    logSink_syslog,
    #endif

    #ifdef PHP_WIN32
    logSink_winSysDebug,
    #endif

    logSink_file,

    numberOfLogSinkTypes
};
typedef enum LogSinkType LogSinkType;

extern String logSinkTypeName[ numberOfLogSinkTypes ];
extern LogLevel defaultLogLevelPerSinkType[ numberOfLogSinkTypes ];

#define ELASTIC_APM_FOR_EACH_LOG_SINK_TYPE( logSinkTypeVar ) ELASTIC_APM_FOR_EACH_INDEX_EX( LogSinkType, logSinkTypeVar, numberOfLogSinkTypes )

struct LoggerConfig
{
    LogLevel levelPerSinkType[ numberOfLogSinkTypes ];
    String file;
};
typedef struct LoggerConfig LoggerConfig;

enum { maxLoggerReentrancyDepth = 2 };

struct Logger
{
    LoggerConfig config;
    char* messageBuffer;
    char* auxMessageBuffer;
    LogLevel maxEnabledLevel;
    UInt8 reentrancyDepth;
    bool fileFailed;
};
typedef struct Logger Logger;

ResultCode constructLogger( Logger* logger );
void reconfigureLogger( Logger* logger, const LoggerConfig* newConfig, LogLevel generalLevel );
void destructLogger( Logger* logger );

void logWithLogger(
        Logger* logger /* <- argument #1 */
        , bool isForced
        , LogLevel statementLevel
        , StringView category
        , StringView filePath
        , UInt lineNumber
        , StringView funcName
        , String msgPrintfFmt /* <- printf format is argument #7 */
        , ...                /* <- arguments for printf format placeholders start from argument #9 */
) ELASTIC_APM_PRINTF_ATTRIBUTE( /* printfFmtPos: */ 8, /* printfFmtArgsPos: */ 9 );

void vLogWithLogger(
        Logger* logger
        , bool isForced
        , LogLevel statementLevel
        , StringView category
        , StringView filePath
        , UInt lineNumber
        , StringView funcName
        , String msgPrintfFmt
        , va_list msgPrintfFmtArgs
);

LogLevel calcMaxEnabledLogLevel( LogLevel levelPerSinkType[ numberOfLogSinkTypes ] );

Logger* getGlobalLogger();

#define ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, fmt, ... ) \
    do { \
        Logger* const globalStateLogger = getGlobalLogger(); \
        if ( globalStateLogger->maxEnabledLevel >= (statementLevel) ) \
        { \
            logWithLogger( \
                globalStateLogger, \
                /* isForced: */ false, \
                (statementLevel), \
                ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_CURRENT_LOG_CATEGORY ), \
                ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FILE__ ), \
                __LINE__, \
                ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ), \
                (fmt) , ##__VA_ARGS__ ); \
        } \
    } while ( 0 )

#define ELASTIC_APM_LOG_CRITICAL( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_critical, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_ERROR( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_error, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_WARNING( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_warning, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_NOTICE( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_notice, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_INFO( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_info, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_DEBUG( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_debug, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_TRACE( fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( logLevel_trace, fmt, ##__VA_ARGS__ )

#define ELASTIC_APM_LOG_FUNCTION_ENTRY_WITH_LEVEL( statementLevel ) ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, "%s", "Entered" )
#define ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY() ELASTIC_APM_LOG_FUNCTION_ENTRY_WITH_LEVEL( logLevel_trace )
#define ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY() ELASTIC_APM_LOG_FUNCTION_ENTRY_WITH_LEVEL( logLevel_debug )

#define ELASTIC_APM_LOG_FUNCTION_ENTRY_MSG_WITH_LEVEL( statementLevel, fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, "%s" fmt, "Entered: ", ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( fmt, ... ) ELASTIC_APM_LOG_FUNCTION_ENTRY_MSG_WITH_LEVEL( logLevel_trace, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( fmt, ... ) ELASTIC_APM_LOG_FUNCTION_ENTRY_MSG_WITH_LEVEL( logLevel_debug, fmt, ##__VA_ARGS__ )

#define ELASTIC_APM_LOG_FUNCTION_EXIT_WITH_LEVEL( statementLevel ) ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, "%s", "Exiting" )
#define ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT() ELASTIC_APM_LOG_FUNCTION_EXIT_WITH_LEVEL( logLevel_trace )
#define ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT() ELASTIC_APM_LOG_FUNCTION_EXIT_WITH_LEVEL( logLevel_debug )

#define ELASTIC_APM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( statementLevel, fmt, ... ) ELASTIC_APM_LOG_WITH_LEVEL( statementLevel, "%s" fmt, "Exiting: ", ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_TRACE_FUNCTION_EXIT_MSG( fmt, ... ) ELASTIC_APM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( logLevel_trace, fmt, ##__VA_ARGS__ )
#define ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( fmt, ... ) ELASTIC_APM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( logLevel_debug, fmt, ##__VA_ARGS__ )

#define ELASTIC_APM_FORCE_LOG_CRITICAL( fmt, ... ) \
    do { \
        logWithLogger( \
            getGlobalLogger(), \
            /* isForced: */ true, \
            logLevel_critical, \
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_CURRENT_LOG_CATEGORY ), \
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FILE__ ), \
            __LINE__, \
            ELASTIC_APM_STRING_LITERAL_TO_VIEW( __FUNCTION__ ), \
            (fmt) , ##__VA_ARGS__ ); \
    } while ( 0 )

static inline
String streamLogLevel( LogLevel level, TextOutputStream* txtOutStream )
{
    if ( level == logLevel_not_set )
        return streamStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "not_set" ), txtOutStream );

    if ( level >= numberOfLogLevels )
        return streamInt( level, txtOutStream );

    return streamString( logLevelNames[ level ], txtOutStream );
}

#define ELASTIC_APM_LOG_CATEGORY_EXT_INFRA "Ext-Infra"
#define ELASTIC_APM_LOG_CATEGORY_CONFIG "Configuration"
#define ELASTIC_APM_LOG_CATEGORY_EXT_API "Ext-API"
#define ELASTIC_APM_LOG_CATEGORY_ASSERT "Assert"
#define ELASTIC_APM_LOG_CATEGORY_LIFECYCLE "Lifecycle"
#define ELASTIC_APM_LOG_CATEGORY_LOG "Log"
#define ELASTIC_APM_LOG_CATEGORY_MEM_TRACKER "MemoryTracker"
#define ELASTIC_APM_LOG_CATEGORY_PLATFORM "Platform"
#define ELASTIC_APM_LOG_CATEGORY_C_TO_PHP "C-to-PHP"
#define ELASTIC_APM_LOG_CATEGORY_SUPPORT "Supportability"
#define ELASTIC_APM_LOG_CATEGORY_SYS_METRICS "SystemMetrics"
#define ELASTIC_APM_LOG_CATEGORY_UTIL "Util"