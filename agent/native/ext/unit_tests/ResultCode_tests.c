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
#include "ResultCode.h"

static
void test_isValidResultCode( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_FOR_EACH_INDEX( resultCode, numberOfResultCodes )
    {
        ELASTIC_APM_CMOCKA_ASSERT_MSG( isValidResultCode( resultCode ), "resultCode: %d", (int)resultCode );
    }
}

static
void test_resultCodeToString( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ELASTIC_APM_FOR_EACH_INDEX( resultCode, numberOfResultCodes )
    {
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( resultCodeNames[ resultCode ].begin, resultCodeToString( resultCode ), "resultCode: %d", (int)resultCode );
    }

    int valuesUnknownResultCodes[] = { -1, INT_MIN, numberOfResultCodes, numberOfResultCodes + 1, numberOfResultCodes + 10, 2 * numberOfResultCodes };
    ELASTIC_APM_FOR_EACH_INDEX( i, ELASTIC_APM_STATIC_ARRAY_SIZE( valuesUnknownResultCodes ) )
    {
        int value = valuesUnknownResultCodes[ i ];
        String valueAsResultCodeString = resultCodeToString( value );
        ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( ELASTIC_APM_UNKNOWN_RESULT_CODE_AS_STRING, valueAsResultCodeString, "i: %d, value: %d", (int)i, value );
    }
}

int run_ResultCode_tests( int argc, const char* argv[] )
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_isValidResultCode ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_resultCodeToString ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
