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
#include <limits.h>
#include "time_util.h"
#include "util.h"

typedef bool ( * IsValidUnitsGenericWrapper )( int value );
typedef String ( * UnitsToStringGenericWrapper )( int value );
typedef Int64 ( * GetValueInUnitsGenericWrapper )( const void* valueWithUnits );
typedef int ( * GetUnitsGenericWrapper )( const void* valueWithUnits );
typedef ResultCode ( * ParseValueWithUnitsGenericWrapper )( StringView inputString, int defaultUnits, /* out */ void* result );
typedef void ( * AssignValueWithUnitsGenericWrapper )( const void* src, /* out */ void* dst );
typedef void* ( * AllocValueWithUnitsGenericWrapper )();
typedef void ( * FillValueWithUnitsGenericWrapper )( Int64 valueInUnits, int units, void* dst );

struct UnitsTypeMetaData
{
    String dbgUnitsTypeAsString;
    int numberOfUnits;
    StringView* unitsNames;
    IsValidUnitsGenericWrapper isValidUnits;
    String invalidUnitsAsString;
    IntArrayView invalidUnits;
    UnitsToStringGenericWrapper unitsToString;
    GetValueInUnitsGenericWrapper getValueInUnits;
    GetUnitsGenericWrapper getUnits;
    ParseValueWithUnitsGenericWrapper parseValueWithUnits;
    const void* invalidValueWithUnits;
    AssignValueWithUnitsGenericWrapper assignValueWithUnits;
    AllocValueWithUnitsGenericWrapper allocValueWithUnits;
    FillValueWithUnitsGenericWrapper fillValueWithUnits;
};
typedef struct UnitsTypeMetaData UnitsTypeMetaData;

#define ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( prefix, unitsPart, suffix ) ELASTIC_APM_PP_CONCAT( prefix, ELASTIC_APM_PP_CONCAT( unitsPart, suffix ) )

#define ELASTIC_APM_UNITS_STRUCT_NAME( UnitsType ) ELASTIC_APM_PP_CONCAT( UnitsType, Units )

#define ELASTIC_APM_IS_VALID_UNITS( UnitsType ) ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( isValid, UnitsType, Units )

#define ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( numberOf, UnitsType, Units )

#define ELASTIC_APM_UNITS_NAMES( unitsPrefix ) ELASTIC_APM_PP_CONCAT( unitsPrefix, UnitsNames )

#define ELASTIC_APM_IS_VALID_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( isValid, UnitsType, UnitsGenericWrapper )

#define ELASTIC_APM_DEFINE_IS_VALID_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    bool ELASTIC_APM_IS_VALID_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( int value ) \
    { \
        return ELASTIC_APM_IS_VALID_UNITS( UnitsType )( (ELASTIC_APM_UNITS_STRUCT_NAME( UnitsType ))value ); \
    }

#define ELASTIC_APM_INVALID_UNITS_ARRAY_NAME( unitsPrefix ) ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( g_, unitsPrefix, InvalidUnits )

#define ELASTIC_APM_DEFINE_INVALID_UNITS( UnitsType, unitsPrefix ) \
            static int ELASTIC_APM_INVALID_UNITS_ARRAY_NAME( unitsPrefix )[] = { \
                INT_MIN \
                , -ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) \
                , -1 \
                , ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) \
                , ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) + 1 \
                , ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) + 10 \
                , 2 * ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) \
            }

#define ELASTIC_APM_UNITS_TO_STRING_GENERIC_WRAPPER_FUNC_NAME( unitsPrefix ) \
    ELASTIC_APM_PP_CONCAT( unitsPrefix, UnitsToStringGenericWrapper )

#define ELASTIC_APM_DEFINE_UNITS_TO_STRING_GENERIC_WRAPPER_FUNC( UnitsType, unitsPrefix ) \
    static \
    String ELASTIC_APM_UNITS_TO_STRING_GENERIC_WRAPPER_FUNC_NAME( unitsPrefix )( int value ) \
    { \
        return ELASTIC_APM_PP_CONCAT( unitsPrefix, UnitsToString )( (ELASTIC_APM_UNITS_STRUCT_NAME( UnitsType ))value ); \
    }

#define ELASTIC_APM_GET_VALUE_IN_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( get, UnitsType, ValueInUnitsGenericWrapper )

#define ELASTIC_APM_DEFINE_GET_VALUE_IN_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    Int64 ELASTIC_APM_GET_VALUE_IN_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( const void* valueWithUnits ) \
    { \
        return ((const UnitsType*)valueWithUnits)->valueInUnits; \
    }

#define ELASTIC_APM_GET_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( get, UnitsType, UnitsGenericWrapper )

#define ELASTIC_APM_DEFINE_GET_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    int ELASTIC_APM_GET_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( const void* valueWithUnits ) \
    { \
        return ((const UnitsType*)valueWithUnits)->units; \
    }

#define ELASTIC_APM_PARSE_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( parse, UnitsType, GenericWrapper )

#define ELASTIC_APM_DEFINE_PARSE_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    ResultCode ELASTIC_APM_PARSE_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( StringView inputString, int defaultUnits, /* out */ void* result ) \
    { \
        return ELASTIC_APM_PP_CONCAT( parse, UnitsType )( inputString, (ELASTIC_APM_UNITS_STRUCT_NAME( UnitsType ))defaultUnits, (UnitsType*)result ); \
    }

#define ELASTIC_APM_ASSIGN_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( assign, UnitsType, GenericWrapper )

#define ELASTIC_APM_DEFINE_ASSIGN_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    void ELASTIC_APM_ASSIGN_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( const void* src, /* out */ void* dst ) \
    { \
        *((UnitsType*)dst) = *((const UnitsType*)src); \
    }

#define ELASTIC_APM_ALLOC_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( alloc, UnitsType, GenericWrapper )

#define ELASTIC_APM_DEFINE_ALLOC_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    void* ELASTIC_APM_ALLOC_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )() \
    { \
        return malloc( sizeof( UnitsType ) ); \
    }

#define ELASTIC_APM_FILL_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( fill, UnitsType, GenericWrapper )

#define ELASTIC_APM_DEFINE_FILL_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static \
    void ELASTIC_APM_FILL_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType )( Int64 valueInUnits, int units, void* dst ) \
    { \
        ((UnitsType*)dst)->valueInUnits = valueInUnits; \
        ((UnitsType*)dst)->units = units; \
    }

#define ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( unitsPrefix ) ELASTIC_APM_BUILD_NAME_WITH_UNITS_TYPE( g_, unitsPrefix, MetaData )

#define ELASTIC_APM_DEFINE_VALUE_WITH_UNITS_TYPE_META_DATA( UnitsType, unitsPrefix, invalidUnitsAsStringParam, pInvalidValueWithUnitsParam ) \
    ELASTIC_APM_DEFINE_IS_VALID_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_INVALID_UNITS( UnitsType, unitsPrefix ); \
    ELASTIC_APM_DEFINE_UNITS_TO_STRING_GENERIC_WRAPPER_FUNC( UnitsType, unitsPrefix ) \
    ELASTIC_APM_DEFINE_GET_VALUE_IN_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_GET_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_PARSE_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_ASSIGN_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_ALLOC_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    ELASTIC_APM_DEFINE_FILL_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC( UnitsType ) \
    static UnitsTypeMetaData ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( unitsPrefix ) = { \
        .dbgUnitsTypeAsString = ELASTIC_APM_PP_STRINGIZE( UnitsType ) \
        , .numberOfUnits = ELASTIC_APM_NUMBER_OF_UNITS( UnitsType ) \
        , .unitsNames = ELASTIC_APM_UNITS_NAMES( unitsPrefix ) \
        , .isValidUnits = &( ELASTIC_APM_IS_VALID_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) ) \
        , .invalidUnitsAsString = (invalidUnitsAsStringParam)                                          \
        , .invalidUnits = ELASTIC_APM_STATIC_ARRAY_TO_VIEW( IntArrayView, ELASTIC_APM_INVALID_UNITS_ARRAY_NAME( unitsPrefix ) ) \
        , .unitsToString = &( ELASTIC_APM_UNITS_TO_STRING_GENERIC_WRAPPER_FUNC_NAME( unitsPrefix ) ) \
        , .getValueInUnits = &( ELASTIC_APM_GET_VALUE_IN_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) ) \
        , .getUnits = &( ELASTIC_APM_GET_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) ) \
        , .parseValueWithUnits = &( ELASTIC_APM_PARSE_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) ) \
        , .invalidValueWithUnits = (pInvalidValueWithUnitsParam) \
        , .assignValueWithUnits = ELASTIC_APM_ASSIGN_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
        , .allocValueWithUnits = ELASTIC_APM_ALLOC_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
        , .fillValueWithUnits = ELASTIC_APM_FILL_VALUE_WITH_UNITS_GENERIC_WRAPPER_FUNC_NAME( UnitsType ) \
    }

static
void impl_test_isValidUnits( UnitsTypeMetaData unitsTypeMetaData )
{
    ELASTIC_APM_FOR_EACH_INDEX( validValue, unitsTypeMetaData.numberOfUnits )
    {
        ELASTIC_APM_CMOCKA_ASSERT_MSG( unitsTypeMetaData.isValidUnits( validValue ), "%s units validValue: %d", unitsTypeMetaData.dbgUnitsTypeAsString, (int)validValue );
    }

    int invalidValues[] = { -1, -unitsTypeMetaData.numberOfUnits, unitsTypeMetaData.numberOfUnits, unitsTypeMetaData.numberOfUnits + 1 };
    ELASTIC_APM_FOR_EACH_INDEX( invalidValueIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( invalidValues ) )
    {
        int invalidValue = invalidValues[ invalidValueIndex ];
        ELASTIC_APM_CMOCKA_ASSERT_MSG( ! unitsTypeMetaData.isValidUnits( invalidValue ), "%s units: %d", unitsTypeMetaData.dbgUnitsTypeAsString, invalidValue );
    }
}

static
void impl_test_unitsToString( UnitsTypeMetaData unitsTypeMetaData )
{
    int numberOfUnits = unitsTypeMetaData.numberOfUnits;
    ELASTIC_APM_FOR_EACH_INDEX_EX( int, validUnits, numberOfUnits )
    {
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( unitsTypeMetaData.unitsNames[ validUnits ].begin, unitsTypeMetaData.unitsToString( (int) validUnits )
                                                , "%s units validValue: %d", unitsTypeMetaData.dbgUnitsTypeAsString, validUnits );
    }

    ELASTIC_APM_FOR_EACH_INDEX( invalidValueIndex, unitsTypeMetaData.invalidUnits.size )
    {
        int invalidValue = unitsTypeMetaData.invalidUnits.values[ invalidValueIndex ];
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( unitsTypeMetaData.invalidUnitsAsString, unitsTypeMetaData.unitsToString( invalidValue )
                                                , "%s units invalidValue: %d", unitsTypeMetaData.dbgUnitsTypeAsString, invalidValue );
    }
}

static
size_t numberOfValidUnits( StringView unitsBase )
{
    if ( unitsBase.length == 0 )
    {
        return 1;
    }

    if ( unitsBase.length == 1 )
    {
        return 2;
    }

    return /* upper at each position */ unitsBase.length + /* all lower + upper */ 2;
}

static
void generateValidUnits( StringView unitsBase, size_t variantIndex, char* buffer )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, unitsBase.length )
    {
        buffer[ i ] = charToLowerCase( unitsBase.begin[ i ] );
    }
    buffer[ unitsBase.length ] = '\0';

    if ( unitsBase.length == 0 )
    {
        return ;
    }

    if ( unitsBase.length == 1 )
    {
        if ( variantIndex > 0 )
        {
            buffer[ 0 ] = charToUpperCase( buffer[ 0 ] );
        }
        return;
    }

    if ( variantIndex < unitsBase.length )
    {
        buffer[ variantIndex ] = charToUpperCase( buffer[ variantIndex ] );
        return;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, unitsBase.length )
    {
        buffer[ i ] = charToUpperCase( unitsBase.begin[ i ] );
    }
}

static
void assertEqualValuesWithUnitsGeneric( UnitsTypeMetaData unitsTypeMetaData, StringView inputString, const void* expected, const void* actual )
{
    ELASTIC_APM_CMOCKA_ASSERT_MSG( unitsTypeMetaData.getUnits( expected ) == unitsTypeMetaData.getUnits( actual )
                                   , "units: %s; inputString: %s; units: expected: %s (as int: %d), actual: %s (as int: %d)"
                                   , unitsTypeMetaData.dbgUnitsTypeAsString, inputString.begin
                                   , unitsTypeMetaData.unitsToString( unitsTypeMetaData.getUnits( expected ) ), unitsTypeMetaData.getUnits( expected )
                                   , unitsTypeMetaData.unitsToString( unitsTypeMetaData.getUnits( actual ) ), unitsTypeMetaData.getUnits( actual ) );

    ELASTIC_APM_CMOCKA_ASSERT_MSG( unitsTypeMetaData.getValueInUnits( expected ) == unitsTypeMetaData.getValueInUnits( actual )
                                   , "units: %s; inputString: %s; valueInUnits: expected: %"PRId64", actual: %"PRId64
                                   , unitsTypeMetaData.dbgUnitsTypeAsString, inputString.begin
                                   , unitsTypeMetaData.getValueInUnits( expected ), unitsTypeMetaData.getValueInUnits( actual ) );
}

static
void impl_test_one_parse( UnitsTypeMetaData unitsTypeMetaData, StringView inputString, int defaultUnits, const void* expected )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    void* actual = unitsTypeMetaData.allocValueWithUnits();
    unitsTypeMetaData.assignValueWithUnits( unitsTypeMetaData.invalidValueWithUnits, /* dst */ actual );
    ResultCode expectedResultCode = expected == NULL ? resultParsingFailed : resultSuccess;
    ResultCode actualResultCode = unitsTypeMetaData.parseValueWithUnits( inputString, defaultUnits, /* out */ actual );
    ELASTIC_APM_CMOCKA_ASSERT_MSG(
            actualResultCode == expectedResultCode
            , "units: %s; expectedResultCode: %s (%d), actualResultCode: %s (%d), inputString: `%s', defaultUnits: %s"
            , unitsTypeMetaData.dbgUnitsTypeAsString
            , resultCodeToString( expectedResultCode ), (int)expectedResultCode
            , resultCodeToString( actualResultCode ), (int)actualResultCode
            , streamStringView( inputString, &txtOutStream ), unitsTypeMetaData.unitsToString( defaultUnits ) );
    assertEqualValuesWithUnitsGeneric( unitsTypeMetaData, inputString, expected == NULL ? unitsTypeMetaData.invalidValueWithUnits : expected, actual );
    free( actual );
    actual = NULL;
}

static
void impl_test_one_valid_parse( UnitsTypeMetaData unitsTypeMetaData, String whiteSpace, String units, const void* expected )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String inputString = streamPrintf( &txtOutStream, "%s%"PRId64"%s%s%s", whiteSpace, unitsTypeMetaData.getValueInUnits( expected ), whiteSpace, units, whiteSpace );
    int expectedUnits = unitsTypeMetaData.getUnits( expected );
    int differentUnits = ( expectedUnits + 1 ) % unitsTypeMetaData.numberOfUnits;
    ELASTIC_APM_CMOCKA_ASSERT( unitsTypeMetaData.isValidUnits( differentUnits ) );
    ELASTIC_APM_CMOCKA_ASSERT( differentUnits != expectedUnits );
    int defaultUnits = isEmtpyString( units ) ? expectedUnits : differentUnits;
    impl_test_one_parse( unitsTypeMetaData, stringToView( inputString ), defaultUnits, expected );
}

static
void impl_test_one_invalid_parse( UnitsTypeMetaData unitsTypeMetaData, String whiteSpace, String invalidValue, int defaultUnits )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String inputString = streamPrintf( &txtOutStream, "%s%s%s", whiteSpace, invalidValue, whiteSpace );
    impl_test_one_parse( unitsTypeMetaData, stringToView( inputString ), defaultUnits, /* expected */ NULL );
}

static
void impl_test_parse( UnitsTypeMetaData unitsTypeMetaData )
{
    ///////////////////////////////////////////////////////////////////
    //
    // Valid
    //

    Int64 validValueInUnitsVariants[] = {
            0, 1, 234, INT8_MAX, INT16_MAX, 567890, INT_MAX, INT32_MAX
            , -1, -234, -INT8_MAX, INT8_MIN, -567890, -INT_MAX, INT_MIN, -INT32_MAX, INT32_MIN
    };
    String validWhiteSpaceVariants[] = { "", " ", "  ",  "\t",  " \t " };
    bool includeUnitsVariants[] = { true, false };
    enum { generatedUnitsBufferSize = 2 +1 /* +1 for terminating '\0' */ };
    char generatedUnitsBuffer[ generatedUnitsBufferSize ];
    void* expected = unitsTypeMetaData.allocValueWithUnits();

    ELASTIC_APM_FOR_EACH_INDEX( validValueInUnitsVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validValueInUnitsVariants ) )
    {
        Int64 valueInUnits = validValueInUnitsVariants[ validValueInUnitsVariantIndex ];
        ELASTIC_APM_FOR_EACH_INDEX( whiteSpaceVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validWhiteSpaceVariants ) )
        {
            String whiteSpace = validWhiteSpaceVariants[ whiteSpaceVariantIndex ];
            ELASTIC_APM_FOR_EACH_INDEX_EX( int, units, unitsTypeMetaData.numberOfUnits )
            {
                unitsTypeMetaData.fillValueWithUnits( valueInUnits, units, /* dst */ expected );
                ELASTIC_APM_FOR_EACH_INDEX( includeUnitsVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( includeUnitsVariants ) )
                {
                    bool includeUnits = includeUnitsVariants[ includeUnitsVariantIndex ];
                    StringView unitsBase = includeUnits ? unitsTypeMetaData.unitsNames[ units ] : makeEmptyStringView();
                    ELASTIC_APM_CMOCKA_ASSERT_MSG( generatedUnitsBufferSize >= ( unitsBase.length + 1 )
                                                   , "generatedUnitsBufferSize: %d, unitsBase [length: %d]: %.*s"
                                                   , generatedUnitsBufferSize, ((int)unitsBase.length), ((int)unitsBase.length), unitsBase.begin );
                    ELASTIC_APM_FOR_EACH_INDEX( unitsVariantIndex, numberOfValidUnits( unitsBase ) )
                    {
                        generateValidUnits( unitsBase, unitsVariantIndex, generatedUnitsBuffer );
                        ELASTIC_APM_CMOCKA_ASSERT( includeUnits == ( ! isEmtpyString( generatedUnitsBuffer ) ) );
                        impl_test_one_valid_parse( unitsTypeMetaData, whiteSpace, generatedUnitsBuffer, expected );
                    }
                }
            }
        }
    }
    free( expected );
    expected = NULL;

    ELASTIC_APM_FOR_EACH_INDEX( whiteSpaceVariantIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( validWhiteSpaceVariants ) )
    {
        String whiteSpace = validWhiteSpaceVariants[ whiteSpaceVariantIndex ];
        ELASTIC_APM_FOR_EACH_INDEX_EX( int, defaultUnitsAsInt, unitsTypeMetaData.numberOfUnits )
        {
            DurationUnits defaultUnits = (DurationUnits)defaultUnitsAsInt;

            ///////////////////////////////////////////////////////////////////
            //
            // Empty value in units
            //

            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "", defaultUnits );

            ///////////////////////////////////////////////////////////////////
            //
            // Invalid numeric part
            //

            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "0x1", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "x0", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "0x", defaultUnits );

            ///////////////////////////////////////////////////////////////////
            //
            // Invalid separator
            //

            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1-", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1:", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1.", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 - ", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 : ", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 . ", defaultUnits );

            ///////////////////////////////////////////////////////////////////
            //
            // Invalid units
            //

            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 a", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 ab", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "1 abc", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "0 m1", defaultUnits );
            impl_test_one_invalid_parse( unitsTypeMetaData, whiteSpace, /* invalidValue */ "0 1m", defaultUnits );
        }
    }
}

static Duration g_invalidDuration = (Duration){ .valueInUnits = INT64_MIN, .units = numberOfDurationUnits };
ELASTIC_APM_DEFINE_VALUE_WITH_UNITS_TYPE_META_DATA( Duration, duration, ELASTIC_APM_UNKNOWN_DURATION_UNITS_AS_STRING, &g_invalidDuration );
static Size g_invalidSize = (Size){ .valueInUnits = INT64_MIN, .units = numberOfSizeUnits };
ELASTIC_APM_DEFINE_VALUE_WITH_UNITS_TYPE_META_DATA( Size, size, ELASTIC_APM_UNKNOWN_SIZE_UNITS_AS_STRING, &g_invalidSize );

static
void test_isValidDurationUnits( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_isValidUnits( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( duration ) );
}

static
void test_isValidSizeUnits( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_isValidUnits( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( size ) );
}

static
void test_durationUnitsToString( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_unitsToString( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( duration )  );
}

static
void test_sizeUnitsToString( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_unitsToString( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( size )  );
}

static
void test_parseDuration( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_parse( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( duration )  );
}

static
void test_parseSize( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    impl_test_parse( ELASTIC_APM_UNITS_META_DATA_GLOBAL_VAR_NAME( size )  );
}

int run_parse_value_with_units_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_isValidSizeUnits ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_isValidDurationUnits ),

        ELASTIC_APM_CMOCKA_UNIT_TEST( test_durationUnitsToString ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_sizeUnitsToString ),

        ELASTIC_APM_CMOCKA_UNIT_TEST( test_parseDuration ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_parseSize ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
