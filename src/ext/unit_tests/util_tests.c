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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "TextOutputStream.h"
#include <limits.h>

static
void areStringViewsEqual_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "A" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "a" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "aBc" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "AbC" ) ) );

    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( NULL, 0 ), makeStringView( NULL, 0 ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( NULL, 0 ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringViewsEqual( makeStringView( "x\0y", 3 ), makeStringView( "x\0yz", 3 ) ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringViewsEqual( makeStringView( "x\0y", 3 ), makeStringView( "x\0z", 3 ) ) );
}

static
void areStringsEqualIgnoringCase_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "", "" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "A", "A" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "A", "a" ) );
    ELASTIC_APM_CMOCKA_ASSERT( areStringsEqualIgnoringCase( "aBc", "AbC" ) );

    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "a", "" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "", "a" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "aBc1", "AbC" ) );
    ELASTIC_APM_CMOCKA_ASSERT( ! areStringsEqualIgnoringCase( "aBc", "AbC1" ) );
}

static
void calcAlignedSize_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 0, 8 ), 0 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 1, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 7, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 8, 8 ), 8 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 9, 8 ), 16 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 15, 8 ), 16 );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( calcAlignedSize( 16, 8 ), 16 );
}

static void trim_StringView_test( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "ABC" ) ), "ABC" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( " ABC" ) ), "ABC" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "ABC\t" ) ), "ABC" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( " AB\tC\r\n" ) ), "AB\tC" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" ) ), "" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( " " ) ), "" );
    ELASTIC_APM_CMOCKA_ASSERT_STRING_VIEW_EQUAL_LITERAL( trimStringView( ELASTIC_APM_STRING_LITERAL_TO_VIEW( " \n\r\t" ) ), "" );
}

static void impl_test_one_parseDecimalInteger( String inputString, const Int64* expected )
{
    ResultCode expectedResultCode = expected == NULL ? resultParsingFailed : resultSuccess;
    Int64 dummy = INT64_MAX;
    Int64 actual = dummy;
    ResultCode actualResultCode = parseDecimalInteger( stringToView( inputString ), &actual );
    ELASTIC_APM_CMOCKA_ASSERT_MSG(
            actualResultCode == expectedResultCode
            , "expectedResultCode: %s (%d), parseDurationResultCode: %s (%d), inputString: `%s'"
            , resultCodeToString( expectedResultCode ), (int)expectedResultCode
            , resultCodeToString( actualResultCode ), (int)actualResultCode
            , inputString );
    if ( expected == NULL )
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( dummy, actual );
    }
    else
    {
        ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( *expected, actual );
    }
}

static void impl_test_one_valid_parseDecimalInteger( String whiteSpace, Int64 expected )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String inputString = streamPrintf( &txtOutStream, "%s%"PRId64"%s", whiteSpace, expected, whiteSpace );

    impl_test_one_parseDecimalInteger( inputString, &expected );
}

static void impl_test_one_invalid_parseDecimalInteger( String whiteSpace, String invalidValue )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String inputString = streamPrintf( &txtOutStream, "%s%s%s", whiteSpace, invalidValue, whiteSpace );

    impl_test_one_parseDecimalInteger( inputString, /* expected */ NULL );
}

static void test_parseDecimalInteger( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    Int64 validValueVariants[] = { 0, 1, -1, 234, -567, INT_MAX, INT_MIN };
    String validWhiteSpaceVariants[] = { "", " ", "  ",  "\t",  " \t " };

    ///////////////////////////////////////////////////////////////////
    //
    // Valid
    //

    ELASTIC_APM_FOR_EACH_INDEX( valueVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validValueVariants ) )
    {
        Int64 value = validValueVariants[ valueVariantIndex ];
        ELASTIC_APM_FOR_EACH_INDEX( whiteSpaceVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validWhiteSpaceVariants ) )
        {
            String whiteSpace = validWhiteSpaceVariants[ whiteSpaceVariantIndex ];
            impl_test_one_valid_parseDecimalInteger( whiteSpace, value );
        }
    }

    ///////////////////////////////////////////////////////////////////
    //
    // Invalid
    //

    ELASTIC_APM_FOR_EACH_INDEX( whiteSpaceVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validWhiteSpaceVariants ) )
    {
        String whiteSpace = validWhiteSpaceVariants[ whiteSpaceVariantIndex ];
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "a" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "z" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "abc" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "0x1" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "0x" );
        impl_test_one_invalid_parseDecimalInteger( whiteSpace, "x0" );
    }
}

static
void test_sizeUnitsToString( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_FOR_EACH_INDEX( sizeUnits, numberOfSizeUnits )
    {
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( sizeUnitsNames[ sizeUnits ].begin, sizeUnitsToString( sizeUnits ), "sizeUnits: %d", (int)sizeUnits );
    }

    int valuesUnknownSizeUnits[] = { -1, INT_MIN, numberOfSizeUnits, numberOfSizeUnits + 1, numberOfSizeUnits + 10, 2 * numberOfSizeUnits };
    ELASTIC_APM_FOR_EACH_INDEX( i, ELASTIC_APM_STATIC_ARRAY_SIZE( valuesUnknownSizeUnits ) )
    {
        int value = valuesUnknownSizeUnits[ i ];
        String valueAsSizeUnitsString = sizeUnitsToString( value );
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( ELASTIC_APM_UNKNOWN_SIZE_UNITS_AS_STRING, valueAsSizeUnitsString, "i: %d, value: %d", (int)i, value );
    }
}

static
void impl_test_one_sizeToBytes( Size inputSize, Int64 expectedSizeInBytes )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    Int64 actualSizeInBytes = sizeToBytes( inputSize );
    ELASTIC_APM_CMOCKA_ASSERT_MSG(
            actualSizeInBytes == expectedSizeInBytes
            , "inputSize: %s, expectedSizeInBytes: %"PRId64", actualSizeInBytes: %"PRId64
            , streamSize( inputSize, &txtOutStream ), expectedSizeInBytes, actualSizeInBytes );
}

static
void test_sizeToBytes( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    // Test zero
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfSizeUnits )
    {
        impl_test_one_sizeToBytes( makeSize( 0, (SizeUnits)i ), /* expectedSizeInBytes */ 0 );
    }

    Int64 factor[ numberOfSizeUnits ] =
    {
        [ sizeUnits_byte ] = 1,
        [ sizeUnits_kibibyte ] = 1024,
        [ sizeUnits_mebibyte ] = 1024 * 1024,
        [ sizeUnits_gibibyte ] = 1024 * 1024 * 1024,
    };
    Int64 valueInUnitsVariants[] = { 1, 123, 4567890, INT8_MAX, UINT8_MAX, INT16_MAX, UINT16_MAX, INT32_MAX, UINT32_MAX };
    ELASTIC_APM_FOR_EACH_INDEX( valueVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( valueInUnitsVariants ) )
    {
        Int64 valueInUnits = valueInUnitsVariants[ valueVariantIndex ];
        ELASTIC_APM_FOR_EACH_INDEX( i, numberOfSizeUnits )
        {
            Int64 expectedSizeInBytes = valueInUnits * factor[ i ];
            impl_test_one_sizeToBytes( makeSize( valueInUnits, (SizeUnits)i ), expectedSizeInBytes );
        }
    }
}

int run_util_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( areStringViewsEqual_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( areStringsEqualIgnoringCase_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( calcAlignedSize_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( trim_StringView_test ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_parseDecimalInteger ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_sizeUnitsToString ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_sizeToBytes ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
