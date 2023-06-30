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

#include "mock_env_vars.h"
#include "mock_stdlib.h"
#include "basic_macros.h" // ELASTIC_APM_UNUSED
#include "unit_test_util.h"

static bool g_isMockEnvVarsInited = false;
static const StringToStringMap* g_mockEnvVarsMap = NULL;

void initMockEnvVars()
{
    ELASTIC_APM_CMOCKA_ASSERT( ! g_isMockEnvVarsInited );
    ELASTIC_APM_CMOCKA_ASSERT( g_mockEnvVarsMap == NULL );

    g_isMockEnvVarsInited = true;
}

void uninitMockEnvVars()
{
    ELASTIC_APM_CMOCKA_ASSERT( g_isMockEnvVarsInited );
    ELASTIC_APM_CMOCKA_ASSERT( g_mockEnvVarsMap == NULL );

    g_isMockEnvVarsInited = false;
}

void setMockEnvVars( const StringToStringMap* mockEnvVars )
{
    ELASTIC_APM_CMOCKA_ASSERT( g_isMockEnvVarsInited );
    ELASTIC_APM_CMOCKA_ASSERT_VALID_PTR( mockEnvVars );

    g_mockEnvVarsMap = mockEnvVars;
}

void resetMockEnvVars()
{
    ELASTIC_APM_CMOCKA_ASSERT( g_isMockEnvVarsInited );

    g_mockEnvVarsMap = NULL;
}

/**
 * mockGetEnv is used in "ConfigManager.c"
 * via ELASTIC_APM_GETENV_FUNC defined in unit tests' CMakeLists.txt
 *
 * mockGetEnv returns `char*` and not `const char*` on purpose
 * because real <stdlib.h> defines getenv as it's done below
 */
char* mockGetEnv( const char* name )
{
    ELASTIC_APM_CMOCKA_ASSERT( g_isMockEnvVarsInited );

    if ( g_mockEnvVarsMap == NULL ) return getenv( name );

    String value;
    const bool exists = getStringToStringMapEntry( g_mockEnvVarsMap, name, &value );
    return exists ? (char*)value : NULL;
}
ELASTIC_APM_SUPPRESS_UNUSED( mockGetEnv );
