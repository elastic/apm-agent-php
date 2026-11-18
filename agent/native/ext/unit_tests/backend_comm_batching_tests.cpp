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

#include "backend_comm_batching.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"
#include <string>
#include <vector>

#define ASSERT_STR_EQ( expected, actual ) \
    ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( (expected), (actual), "expected: `%s', actual: `%s'", (expected), (actual) )

namespace
{
const char* const meta = "{\"metadata\":{\"service\":{\"name\":\"a\"}}}";
const char* const otherMeta = "{\"metadata\":{\"service\":{\"name\":\"b\"}}}";
const char* const userAgent = "apm-agent-php/1.0";

std::string makeBatch( const std::string& metadataLine, const std::vector< std::string >& eventLines )
{
    std::string result = metadataLine;
    for ( const std::string& line : eventLines )
    {
        result += "\n" + line;
    }
    return result;
}

size_t combine( const std::vector< std::string >& batches, const std::vector< std::string >& userAgents, size_t maxBodySize, std::string& body )
{
    std::vector< EventsBatchView > views;
    for ( size_t i = 0; i < batches.size(); ++i )
    {
        views.push_back( EventsBatchView{ userAgents[ i ], batches[ i ] } );
    }
    return combineEventsBatches( views, maxBodySize, body );
}

size_t combine( const std::vector< std::string >& batches, size_t maxBodySize, std::string& body )
{
    return combine( batches, std::vector< std::string >( batches.size(), userAgent ), maxBodySize, body );
}
}

static void test_splitIntoMetadataLineAndEventLines( void** )
{
    ASSERT_STR_EQ( "m", std::string( getEventsBatchMetadataLine( "m\ne1\ne2" ) ).c_str() );
    ASSERT_STR_EQ( "e1\ne2", std::string( getEventsBatchEventLines( "m\ne1\ne2" ) ).c_str() );

    ASSERT_STR_EQ( "m", std::string( getEventsBatchMetadataLine( "m" ) ).c_str() );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)getEventsBatchEventLines( "m" ).size() );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)getEventsBatchMetadataLine( "" ).size() );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)getEventsBatchEventLines( "" ).size() );
}

static void test_emptyInput( void** )
{
    std::string body = "garbage";
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)combineEventsBatches( {}, 1000, body ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)body.size() );
}

static void test_singleBatchIsUnchanged( void** )
{
    std::string batch = makeBatch( meta, { "{\"span\":1}" } );
    std::string body;
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)combine( { batch }, 1000, body ) );
    ASSERT_STR_EQ( batch.c_str(), body.c_str() );
}

static void test_metadataIsSentOnce( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, { "{\"span\":2}", "{\"span\":3}" } );
    std::string b3 = makeBatch( meta, { "{\"transaction\":4}" } );
    std::string body;

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 3, (int)combine( { b1, b2, b3 }, 100000, body ) );
    std::string expected = makeBatch( meta, { "{\"span\":1}", "{\"span\":2}", "{\"span\":3}", "{\"transaction\":4}" } );
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );
}

static void test_batchWithMetadataOnlyIsConsumedWithoutAddingLines( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, {} );
    std::string b3 = makeBatch( meta, { "{\"span\":3}" } );
    std::string body;

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 3, (int)combine( { b1, b2, b3 }, 100000, body ) );
    std::string expected = makeBatch( meta, { "{\"span\":1}", "{\"span\":3}" } );
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );
}

static void test_stopsAtDifferentMetadata( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, { "{\"span\":2}" } );
    std::string b3 = makeBatch( otherMeta, { "{\"span\":3}" } );
    std::string b4 = makeBatch( meta, { "{\"span\":4}" } );
    std::string body;

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 2, (int)combine( { b1, b2, b3, b4 }, 100000, body ) );
    std::string expected = makeBatch( meta, { "{\"span\":1}", "{\"span\":2}" } );
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );
}

static void test_stopsAtDifferentUserAgent( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, { "{\"span\":2}" } );
    std::string body;

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)combine( { b1, b2 }, { "ua1", "ua2" }, 100000, body ) );
    ASSERT_STR_EQ( b1.c_str(), body.c_str() );
}

static void test_sizeLimit( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, { "{\"span\":2}" } );
    std::string b3 = makeBatch( meta, { "{\"span\":3}" } );
    std::string body;

    // exactly fits two batches
    std::string twoBatches = makeBatch( meta, { "{\"span\":1}", "{\"span\":2}" } );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 2, (int)combine( { b1, b2, b3 }, twoBatches.size(), body ) );
    ASSERT_STR_EQ( twoBatches.c_str(), body.c_str() );

    // one byte too small for two batches
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)combine( { b1, b2, b3 }, twoBatches.size() - 1, body ) );
    ASSERT_STR_EQ( b1.c_str(), body.c_str() );
}

static void test_firstBatchIsAlwaysTakenEvenIfOverLimit( void** )
{
    std::string b1 = makeBatch( meta, { "{\"span\":1}" } );
    std::string b2 = makeBatch( meta, { "{\"span\":2}" } );
    std::string body;

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)combine( { b1, b2 }, 1, body ) );
    ASSERT_STR_EQ( b1.c_str(), body.c_str() );
}

static void test_batchWithoutNewLine( void** )
{
    std::string body;
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 2, (int)combine( { meta, meta }, 100000, body ) );
    ASSERT_STR_EQ( meta, body.c_str() );
}

static void test_trailingNewLinesDoNotProduceEmptyLines( void** )
{
    std::string meta1 = meta;
    std::string body;

    // trailing '\n' in a non-first batch
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 3, (int)combine( { meta1 + "\n{\"span\":1}", meta1 + "\n{\"span\":2}\n", meta1 + "\n{\"span\":3}" }, 100000, body ) );
    std::string expected = makeBatch( meta, { "{\"span\":1}", "{\"span\":2}", "{\"span\":3}" } );
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );

    // trailing '\n' in the first batch
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 2, (int)combine( { meta1 + "\n{\"span\":1}\n", meta1 + "\n{\"span\":2}" }, 100000, body ) );
    expected = meta1 + "\n{\"span\":1}\n{\"span\":2}";
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );

    // metadata-only batch with a trailing '\n' adds nothing
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 3, (int)combine( { meta1 + "\n{\"span\":1}", meta1 + "\n", meta1 + "\n{\"span\":3}" }, 100000, body ) );
    expected = makeBatch( meta, { "{\"span\":1}", "{\"span\":3}" } );
    ASSERT_STR_EQ( expected.c_str(), body.c_str() );
}

static void test_helpersUsedForCollectingBatches( void** )
{
    const EventsBatchView first{ "ua", "m\n{\"span\":1}" };
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)canBeCombinedWithFirstBatch( first, EventsBatchView{ "ua", "m\n{\"span\":2}" } ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 1, (int)canBeCombinedWithFirstBatch( first, EventsBatchView{ "ua", "m" } ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)canBeCombinedWithFirstBatch( first, EventsBatchView{ "other ua", "m\n{\"span\":2}" } ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)canBeCombinedWithFirstBatch( first, EventsBatchView{ "ua", "other metadata\n{\"span\":2}" } ) );

    // 1 (for '\n') + length of "{\"span\":2}" (10)
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 11, (int)sizeAddedToCombinedBody( "m\n{\"span\":2}" ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 11, (int)sizeAddedToCombinedBody( "m\n{\"span\":2}\n\n" ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)sizeAddedToCombinedBody( "m" ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)sizeAddedToCombinedBody( "m\n" ) );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 0, (int)sizeAddedToCombinedBody( "" ) );
}

int run_backend_comm_batching_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_splitIntoMetadataLineAndEventLines ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_emptyInput ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_singleBatchIsUnchanged ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_metadataIsSentOnce ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_batchWithMetadataOnlyIsConsumedWithoutAddingLines ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_stopsAtDifferentMetadata ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_stopsAtDifferentUserAgent ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_sizeLimit ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_firstBatchIsAlwaysTakenEvenIfOverLimit ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_batchWithoutNewLine ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_trailingNewLinesDoNotProduceEmptyLines ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_helpersUsedForCollectingBatches ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
