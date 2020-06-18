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

#include "mock_log_custom_sink.h"
#include "unit_test_util.h"
#include "elasticapm_alloc.h"

void setGlobalLoggerLevelForCustomSink( LogLevel levelForCustomSink )
{
    LoggerConfig newConfig = { 0 };
    reconfigureLogger( getGlobalLogger(), &newConfig, /* generalLevel: */ logLevel_off );
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
        ELASTICAPM_CMOCKA_ASSERT( ! mockLogCustomSink->isEnabled );
    }

    if ( mockLogCustomSink->isEnabled )
    {
        ELASTICAPM_ASSERT_VALID_DYNAMIC_ARRAY( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
    }
}

void initMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( ! mockLogCustomSink->isInited );

    ELASTICAPM_ZERO_STRUCT( mockLogCustomSink );
    mockLogCustomSink->isInited = true;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void enableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isInited );

    mockLogCustomSink->isEnabled = true;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void disableMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    clearMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_DESTRUCT_DYNAMIC_ARRAY( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
    mockLogCustomSink->isEnabled = false;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

void uninitMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( ! mockLogCustomSink->isEnabled );

    mockLogCustomSink->isInited = false;

    assertValidMockLogCustomSink( mockLogCustomSink );
}

size_t numberOfStatementsInMockLogCustomSink( const MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    return ELASTICAPM_GET_DYNAMIC_ARRAY_SIZE( MockLogCustomSinkStatement, &mockLogCustomSink->statements );
}

String getStatementInMockLogCustomSinkContent( const MockLogCustomSink* mockLogCustomSink, size_t index )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    MockLogCustomSinkStatement* statement;
    ELASTICAPM_GET_DYNAMIC_ARRAY_ELEMENT_AT( MockLogCustomSinkStatement, &mockLogCustomSink->statements, index, statement );
    return statement->text;
}

void clearMockLogCustomSink( MockLogCustomSink* mockLogCustomSink )
{
    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isEnabled );

    ELASTICAPM_FOR_EACH_DYNAMIC_ARRAY_ELEMENT( MockLogCustomSinkStatement, statement, &mockLogCustomSink->statements )
        ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( strlen( statement->text ) + 1, statement->text );

    ELASTICAPM_REMOVE_ALL_DYNAMIC_ARRAY_ELEMENTS( MockLogCustomSinkStatement, &mockLogCustomSink->statements );

    assertValidMockLogCustomSink( mockLogCustomSink );
}

static MockLogCustomSink g_mockLogCustomSink = { 0 };

MockLogCustomSink* getGlobalMockLogCustomSink()
{
    assertValidMockLogCustomSink( &g_mockLogCustomSink );
    return &g_mockLogCustomSink;
}

/// writeToMockLogCustomSink is used in "log.c"
/// via ELASTICAPM_LOG_CUSTOM_SINK_FUNC defined in unit tests' CMakeLists.txt
void writeToMockLogCustomSink( String text )
{
    ResultCode resultCode;
    MockLogCustomSink* const mockLogCustomSink = getGlobalMockLogCustomSink();
    String textDup = NULL;

    assertValidMockLogCustomSink( mockLogCustomSink );
    ELASTICAPM_CMOCKA_ASSERT( mockLogCustomSink->isInited );

    // When MockLogCustomSink is init-ed but not yet enabled it just discards all log statements it receives.
    if ( ! mockLogCustomSink->isEnabled ) return;

    ELASTICAPM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( text, textDup );

    ELASTICAPM_ADD_TO_DYNAMIC_ARRAY_BACK_IF_FAILED_GOTO(
            MockLogCustomSinkStatement,
            &mockLogCustomSink->statements,
            &((MockLogCustomSinkStatement){ .text = textDup }) );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_CMOCKA_CALL_ASSERT_RESULT_SUCCESS( resultCode );
    return;

    failure:
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( strlen( textDup ) + 1, textDup );
    goto finally;

}
ELASTICAPM_SUPPRESS_UNUSED( writeToMockLogCustomSink );
