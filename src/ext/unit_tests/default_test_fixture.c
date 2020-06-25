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

#include "default_test_fixture.h"
#include "Tracer.h"
#include "mock_assert.h"
#include "mock_log_custom_sink.h"
#include "mock_clock.h"
#include "mock_env_vars.h"
#include "mock_php_ini.h"
#include "unit_test_util.h"

int perTestDefaultSetup( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    //
    // Revert to failed ELASTIC_APM_ASSERT invoking abort() in production code ASAP
    // so that any failed assert in production code triggers a real abort and causes test to fail
    //
    setProductionCodeAssertFailed( vElasticApmAssertFailed );

    initMockLogCustomSink( getGlobalMockLogCustomSink() );
    initMockEnvVars();
    initMockPhpIni();

    constructTracer( getGlobalTracer() );

    enableMockLogCustomSink( getGlobalMockLogCustomSink() );

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( ensureAllComponentsHaveLatestConfig( getGlobalTracer() ) );

    #ifdef PHP_WIN32
    // Disable the abort message box
    if ( ! getGlobalCurrentConfigSnapshot()->allowAbortDialog ) _set_abort_behavior( 0, _WRITE_ABORT_MSG );
    #endif

    return 0;
}

int perTestDefaultTeardown( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    //
    // Revert to failed ELASTIC_APM_ASSERT invoking abort() in production code ASAP
    // so that any failed assert in production code triggers a real abort and causes test to fail
    //
    setProductionCodeAssertFailed( vElasticApmAssertFailed );

    revertToRealCurrentTime();

    disableMockLogCustomSink( getGlobalMockLogCustomSink() );

    destructTracer( getGlobalTracer() );

    uninitMockPhpIni();
    uninitMockEnvVars();
    uninitMockLogCustomSink( getGlobalMockLogCustomSink() );

    return 0;
}
