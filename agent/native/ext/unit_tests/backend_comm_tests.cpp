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

#include "unit_test_util.h"
#include "mock_clock.h"
#include "mock_log_custom_sink.h"

#include "../backend_comm.cpp"

static DataToSendNode makeDataToSendNode( UInt64 id, char* serializedEvents, size_t serializedEventsBufferSize )
{
    DataToSendNode dataToSendNode;
    ELASTIC_APM_ZERO_STRUCT( &dataToSendNode );

    dataToSendNode.id = id;
    dataToSendNode.serializedEvents = ELASTIC_APM_MAKE_STRING_BUFFER( serializedEvents, serializedEventsBufferSize );

    return dataToSendNode;
}

static const char* getOnlyLogStatementText()
{
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMockLogCustomSink().size(), 1 );
    return getGlobalMockLogCustomSink().get( 0 ).c_str();
}

static void timed_out_async_shutdown_with_pending_events_logs_warning( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    setGlobalLoggerLevelForCustomSink( logLevel_warning );
    getGlobalMockLogCustomSink().clear();

    setMockCurrentTime(
            /* years: */ 2026,
            /* months: */ 3,
            /* days: */ 24,
            /* hours: */ 19,
            /* minutes: */ 15,
            /* seconds: */ 18,
            /* microseconds: */ 123456,
            /* secondsAheadUtc: */ -( 5 * 60 * 60 ) );

    char serializedEvents[] = "transaction\nspan\n";
    DataToSendNode firstPendingBatch = makeDataToSendNode( /* id: */ 789, serializedEvents, sizeof( serializedEvents ) );

    BackgroundBackendCommSharedStateSnapshot sharedStateSnapshot;
    ELASTIC_APM_ZERO_STRUCT( &sharedStateSnapshot );
    sharedStateSnapshot.firstDataToSendNode = &firstPendingBatch;
    sharedStateSnapshot.dataToSendTotalSize = 456;
    sharedStateSnapshot.shouldExit = true;

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( getCurrentAbsTimeSpec( &sharedStateSnapshot.shouldExitBy ) );
    --sharedStateSnapshot.shouldExitBy.tv_sec;

    bool shouldBreakLoop = false;
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS(
            backgroundBackendCommThreadFunc_shouldBreakLoop( &sharedStateSnapshot, &shouldBreakLoop ) );

    ELASTIC_APM_CMOCKA_ASSERT( shouldBreakLoop );

    const char* logStatementText = getOnlyLogStatementText();
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE(
            logStatementText, "Async shutdown drain timed out with queued events still pending" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE(
            logStatementText, "remaining queued events will be dropped" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE(
            logStatementText, "total size of queued events: 456" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE(
            logStatementText, "first pending batch ID: 789" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_CONTAINS_IGNORE_CASE(
            logStatementText, "first pending batch size: 17" );
}

static void pending_events_before_shutdown_deadline_do_not_log_warning( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    setGlobalLoggerLevelForCustomSink( logLevel_warning );
    getGlobalMockLogCustomSink().clear();

    setMockCurrentTime(
            /* years: */ 2026,
            /* months: */ 3,
            /* days: */ 24,
            /* hours: */ 19,
            /* minutes: */ 15,
            /* seconds: */ 18,
            /* microseconds: */ 123456,
            /* secondsAheadUtc: */ -( 5 * 60 * 60 ) );

    char serializedEvents[] = "transaction\nspan\n";
    DataToSendNode firstPendingBatch = makeDataToSendNode( /* id: */ 789, serializedEvents, sizeof( serializedEvents ) );

    BackgroundBackendCommSharedStateSnapshot sharedStateSnapshot;
    ELASTIC_APM_ZERO_STRUCT( &sharedStateSnapshot );
    sharedStateSnapshot.firstDataToSendNode = &firstPendingBatch;
    sharedStateSnapshot.dataToSendTotalSize = 456;
    sharedStateSnapshot.shouldExit = true;

    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( getCurrentAbsTimeSpec( &sharedStateSnapshot.shouldExitBy ) );
    ++sharedStateSnapshot.shouldExitBy.tv_sec;

    bool shouldBreakLoop = true;
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS(
            backgroundBackendCommThreadFunc_shouldBreakLoop( &sharedStateSnapshot, &shouldBreakLoop ) );

    ELASTIC_APM_CMOCKA_ASSERT( ! shouldBreakLoop );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( getGlobalMockLogCustomSink().size(), 0 );
}

int run_backend_comm_tests()
{
    const CMUnitTest tests[]
            = {
                    ELASTIC_APM_CMOCKA_UNIT_TEST( timed_out_async_shutdown_with_pending_events_logs_warning ),
                    ELASTIC_APM_CMOCKA_UNIT_TEST( pending_events_before_shutdown_deadline_do_not_log_warning ),
            };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
