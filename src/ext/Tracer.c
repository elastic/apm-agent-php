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

#include "Tracer.h"

#if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

AssertLevel getGlobalAssertLevel()
{
    return getGlobalTracer()->currentAssertLevel;
}

#endif

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
MemoryTracker* getGlobalMemoryTracker()
{
    return &getGlobalTracer()->memTracker;
}
#endif

InternalChecksLevel getGlobalInternalChecksLevel()
{
    return getGlobalTracer()->currentInternalChecksLevel;
}

const ConfigSnapshot* getGlobalCurrentConfigSnapshot()
{
    return getConfigManagerCurrentSnapshot( getGlobalTracer()->configManager );
}

static
void ensureLoggerHasLatestConfig( Logger* logger, const ConfigSnapshot* config )
{
    LoggerConfig loggerConfig = { 0 };

    loggerConfig.levelPerSinkType[ logSink_stderr ] = config->logLevelStderr;
    loggerConfig.levelPerSinkType[ logSink_file ] = config->logLevelFile;
    #ifndef PHP_WIN32
    loggerConfig.levelPerSinkType[ logSink_syslog ] = config->logLevelSyslog;
    #endif
    #ifdef PHP_WIN32
    loggerConfig.levelPerSinkType[ logSink_winSysDebug ] = config->logLevelWinSysDebug;
    #endif

    loggerConfig.file = config->logFile;

    reconfigureLogger( logger, &loggerConfig, config->logLevel );
}

#if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
static
void ensureMemoryTrackerHasLatestConfig( MemoryTracker* memTracker, const ConfigSnapshot* config )
{
    MemoryTrackingLevel levelsDescendingPrecedence[] =
    {
        config->memoryTrackingLevel,
        internalChecksToMemoryTrackingLevel( config->internalChecksLevel )
    };

    MemoryTrackingLevel newLevel = ELASTICAPM_MEMORY_TRACKING_DEFAULT_LEVEL;
    ELASTICAPM_FOR_EACH_INDEX( i, ELASTICAPM_STATIC_ARRAY_SIZE( levelsDescendingPrecedence ) )
    {
        if ( levelsDescendingPrecedence[ i ] == memoryTrackingLevel_not_set ) continue;
        newLevel = levelsDescendingPrecedence[ i ];
        break;
    }

    reconfigureMemoryTracker( memTracker, newLevel, config->abortOnMemoryLeak );
}
#endif // #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )

#if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
static
void ensureAssertHasLatestConfig( Tracer* tracer, const ConfigSnapshot* config )
{
    AssertLevel levelsDescendingPrecedence[] =
    {
        config->assertLevel,
        internalChecksToAssertLevel( config->internalChecksLevel )
    };

    AssertLevel newLevel = ELASTICAPM_ASSERT_DEFAULT_LEVEL;
    ELASTICAPM_FOR_EACH_INDEX( i, ELASTICAPM_STATIC_ARRAY_SIZE( levelsDescendingPrecedence ) )
    {
        if ( levelsDescendingPrecedence[ i ] == assertLevel_not_set ) continue;
        newLevel = levelsDescendingPrecedence[ i ];
        break;
    }
    tracer->currentAssertLevel = newLevel;
}
#endif // #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )

static
void ensureInternalChecksLevelHasLatestConfig( Tracer* tracer, const ConfigSnapshot* config )
{
    tracer->currentInternalChecksLevel =
            ( config->internalChecksLevel == internalChecksLevel_not_set )
            ? ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL
            : config->internalChecksLevel;
}

ResultCode ensureAllComponentsHaveLatestConfig( Tracer* tracer )
{
    ELASTICAPM_ASSERT_VALID_PTR( tracer );

    ResultCode resultCode;
    const ConfigSnapshot* config = NULL;
    bool didConfigChange;

    ELASTICAPM_CALL_IF_FAILED_GOTO( ensureConfigManagerHasLatestConfig( tracer->configManager, &didConfigChange ) );
    if ( ! didConfigChange )
    {
        resultCode = resultSuccess;
        goto finally;
    }

    resultCode = resultSuccess;

    config = getTracerCurrentConfigSnapshot( tracer );
    ensureInternalChecksLevelHasLatestConfig( tracer, config );
    ensureLoggerHasLatestConfig( &tracer->logger, config );
    #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    ensureMemoryTrackerHasLatestConfig( &tracer->memTracker, config );
    #endif
    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    ensureAssertHasLatestConfig( tracer, config );
    #endif

    finally:
    return resultCode;

    failure:
    goto finally;
}

const ConfigSnapshot* getTracerCurrentConfigSnapshot( Tracer* tracer )
{
    return getConfigManagerCurrentSnapshot( tracer->configManager );
}

ResultCode constructTracer( Tracer* tracer )
{
    ELASTICAPM_ASSERT_VALID_PTR( tracer );

    ResultCode resultCode;

    ELASTICAPM_ZERO_STRUCT( tracer );

    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    tracer->currentAssertLevel = ELASTICAPM_ASSERT_DEFAULT_LEVEL;
    #endif

    tracer->currentInternalChecksLevel = ELASTICAPM_INTERNAL_CHECKS_DEFAULT_LEVEL;

    //
    // MemoryTracker needs Logger to report so Logger has to be constructed before MemoryTracker
    //
    ELASTICAPM_CALL_IF_FAILED_GOTO( constructLogger( &tracer->logger ) );

    #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    constructMemoryTracker( &tracer->memTracker );
    #endif

    ELASTICAPM_CALL_IF_FAILED_GOTO( newConfigManager( &tracer->configManager ) );

    resultCode = resultSuccess;
    tracer->isInited = true;

    finally:
    return resultCode;

    failure:
    destructTracer( tracer );
    goto finally;
}

void destructTracer( Tracer* tracer )
{
    ELASTICAPM_ASSERT_VALID_PTR( tracer );

    deleteConfigManagerAndSetToNull( &tracer->configManager );

    //
    // MemoryTracker needs Logger to report so Logger has to be destructed after MemoryTracker
    //
    #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    destructMemoryTracker( &tracer->memTracker );
    #endif

    destructLogger( &tracer->logger );

    ELASTICAPM_ZERO_STRUCT( tracer );
}
