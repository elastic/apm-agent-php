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

#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
//#include "mock_env_vars.h"
//#include "mock_php_ini.h"


static
void dummy( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

}

int run_config_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( dummy ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
