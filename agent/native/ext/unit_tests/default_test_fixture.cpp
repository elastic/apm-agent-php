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

    getGlobalMockLogCustomSink() = {};

    initMockPhpIni();

    constructTracer( getGlobalTracer() );

    getGlobalMockLogCustomSink().enable();

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

    getGlobalMockLogCustomSink().disable();
    getGlobalMockLogCustomSink().clear();

    destructTracer( getGlobalTracer() );

    uninitMockPhpIni();

    return 0;
}
