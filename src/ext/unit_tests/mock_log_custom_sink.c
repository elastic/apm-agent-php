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

#include "mock_log_custom_sink.h"
#include "unit_test_util.h"
#include "elastic_apm_alloc.h"

void setGlobalLoggerLevelForCustomSink( LogLevel levelForCustomSink )
{
    LoggerConfig newConfig = { 0 };
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( reconfigureLogger( getGlobalLogger(), &newConfig, /* generalLevel: */ logLevel_off ) );
    getGlobalLogger()->maxEnabledLevel = levelForCustomSink;
}

struct MockLogCustomSinkStatement
{
    String text;
};
typedef struct MockLogCustomSinkStatement MockLogCustomSinkStatement;

struct MockLogCustomSink
{
    bool isInited;
    bool isEnabled;
    DynamicArray statements;
};

void assertValidMockLogCustomSink( const MockLogCustomSink* mockLogCustomSink )
{
    if ( ! mockLogCustomSink->isInited )
    {
        ELASTIC_APM_CMOCKA_ASSERT( ! mockLogCustomSink->isEnabled );
    }

    if ( mockLogCustomSink->isEnabled )
    {
        ELASTIC_APM_ASSERT_VALID_DYNAMIC_ARRAY( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
    }
}

void initMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( ! mockLogCustomSink->isInited );

    ELASTIC_APM_ZERO_STRUCT( mockLogCustomSink );
    mockLogCustomSink->isInited = true;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void enableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isInited );

    mockLogCustomSink->isEnabled = true;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void disableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    clearMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_DESTRUCT_DYNAMIC_ARRAY( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
    mockLogCustomSink->isEnabled = false;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void uninitMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( ! mockLogCustomSink->isEnabled );

    mockLogCustomSink->isInited = false;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

size_t numberOfStatementsInMockLogCustomSink( const MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    return ELASTIC_APM_GET_DYNAMIC_ARRAY_SIZE( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
}

String getStatementInMockLogCustomSinkContent( const MockLogCustomSink* mockLogCustomSink, size_t index )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    MockLogCustomSinkStatement* statement;
    ELASTIC_APM_GET_DYNAMIC_ARRAY_ELEMENT_AT( MockLogCustomSinkStatement, &mockLogCustomSink->statements, index, statement );
    return statement->text;
}

void clearMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    ELASTIC_APM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( MockLogCustomSinkStatement, statement, &mockLogCustomSink->statements )
        ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( statement->text );

    ELASTIC_APM_REMOVE_ALL_DYNAMIC_ARRAY_ELEMENTS( MockLogCustomSinkStatement, &mockLogCustomSink->statements );

    assertValidMockLogCustomSink( mockLogCustomSink );
}

static MockLogCustomSink g_mockLogCustomSink = { 0 };

MockLogCustomSink* getGlobalMockLogCustomSink()
{
    assertValidMockLogCustomSink( &g_mockLogCustomSink );
    return &g_mockLogCustomSink;
}

/// writeToMockLogCustomSink is used in "log.c"
/// via ELASTIC_APM_LOG_CUSTOM_SINK_FUNC defined in unit tests' CMakeLists.txt
void writeToMockLogCustomSink( String text )
{
    ResultCode resultCode;
    MockLogCustomSink* const mockLogCustomSink = getGlobalMockLogCustomSink();
    String textDup = NULL;

    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTIC_APM_CMOCKA_ASSERT( mockLogCustomSink->isInited );

    // When MockLogCustomSink is init-ed but not yet enabled it just discards all log statements it receives.
    if ( ! mockLogCustomSink->isEnabled ) return;

    ELASTIC_APM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( text, textDup );

    ELASTIC_APM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
            MockLogCustomSinkStatement,
            &mockLogCustomSink->statements,
            &((MockLogCustomSinkStatement){ .text = textDup }) );

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( textDup );
    goto finally;

}
ELASTIC_APM_SUPPRESS_UNUSED( writeToMockLogCustomSink );
