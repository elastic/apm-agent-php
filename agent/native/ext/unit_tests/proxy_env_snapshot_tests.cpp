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

#include "proxy_env_snapshot.h"
#include "cmocka_wrapped_for_unit_tests.h"
#include "unit_test_util.h"
#include "mock_assert.h"
#include <map>
#include <string>

#define ASSERT_STR_EQ( expected, actual ) \
    ELASTIC_APM_CMOCKA_ASSERT_STRING_EQUAL( (expected), (actual), "expected: `%s', actual: `%s'", (expected), (actual) )

namespace
{
std::map< std::string, std::string > g_fakeEnv;
std::map< std::string, int > g_fakeEnvLookups;

const char* fakeGetEnv( const char* name )
{
    ++g_fakeEnvLookups[ name ];
    auto it = g_fakeEnv.find( name );
    return it == g_fakeEnv.end() ? nullptr : it->second.c_str();
}

ProxyEnvSnapshot resolve( const char* serverUrl, std::map< std::string, std::string > env )
{
    g_fakeEnv = std::move( env );
    g_fakeEnvLookups.clear();
    return resolveProxyEnvSnapshot( serverUrl, &fakeGetEnv );
}
} // namespace

static void test_noEnv( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ProxyEnvSnapshot s = resolve( "http://localhost:8200", {} );
    ASSERT_STR_EQ( "", s.proxy.c_str() );
    ASSERT_STR_EQ( "", s.noProxy.c_str() );
}

static void test_httpUsesLowercaseOnly( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    // httpoxy: HTTP_PROXY must never be used
    ProxyEnvSnapshot s = resolve( "http://localhost:8200", { { "HTTP_PROXY", "http://evil:1" } } );
    ASSERT_STR_EQ( "", s.proxy.c_str() );

    s = resolve( "http://localhost:8200", { { "HTTP_PROXY", "http://evil:1" }, { "http_proxy", "http://good:2" } } );
    ASSERT_STR_EQ( "http://good:2", s.proxy.c_str() );

    // https_proxy is irrelevant for http
    s = resolve( "http://localhost:8200", { { "https_proxy", "http://other:3" } } );
    ASSERT_STR_EQ( "", s.proxy.c_str() );
}

static void test_httpsLowerThenUpper( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ProxyEnvSnapshot s = resolve( "HTTPS://host", { { "HTTPS_PROXY", "http://upper:1" } } );
    ASSERT_STR_EQ( "http://upper:1", s.proxy.c_str() );

    s = resolve( "https://host", { { "HTTPS_PROXY", "http://upper:1" }, { "https_proxy", "http://lower:2" } } );
    ASSERT_STR_EQ( "http://lower:2", s.proxy.c_str() );

    // http_proxy is irrelevant for https
    s = resolve( "https://host", { { "http_proxy", "http://other:3" } } );
    ASSERT_STR_EQ( "", s.proxy.c_str() );
}

static void test_allProxyFallback( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    for ( const char* url : { "http://host", "https://host" } )
    {
        ProxyEnvSnapshot s = resolve( url, { { "ALL_PROXY", "http://all-upper:1" } } );
        ASSERT_STR_EQ( "http://all-upper:1", s.proxy.c_str() );

        s = resolve( url, { { "ALL_PROXY", "http://all-upper:1" }, { "all_proxy", "http://all-lower:2" } } );
        ASSERT_STR_EQ( "http://all-lower:2", s.proxy.c_str() );
    }

    // scheme specific variable wins over all_proxy
    ProxyEnvSnapshot s = resolve( "http://host", { { "http_proxy", "http://p:1" }, { "all_proxy", "http://all:2" } } );
    ASSERT_STR_EQ( "http://p:1", s.proxy.c_str() );
}

static void test_emptyValueIsTreatedAsNotSet( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ProxyEnvSnapshot s = resolve( "http://host", { { "http_proxy", "" }, { "all_proxy", "http://all:2" }, { "no_proxy", "" }, { "NO_PROXY", "example.com" } } );
    ASSERT_STR_EQ( "http://all:2", s.proxy.c_str() );
    ASSERT_STR_EQ( "example.com", s.noProxy.c_str() );
}

static void test_noProxy( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    ProxyEnvSnapshot s = resolve( "http://host", { { "NO_PROXY", "upper.com" } } );
    ASSERT_STR_EQ( "upper.com", s.noProxy.c_str() );

    s = resolve( "http://host", { { "NO_PROXY", "upper.com" }, { "no_proxy", "lower.com" } } );
    ASSERT_STR_EQ( "lower.com", s.noProxy.c_str() );
}

static void test_longValuesAreNotTruncated( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    std::string longNoProxy( 10000, 'a' );
    ProxyEnvSnapshot s = resolve( "http://host", { { "no_proxy", longNoProxy } } );
    ELASTIC_APM_CMOCKA_ASSERT_INT_EQUAL( 10000, (int)s.noProxy.size() );
}

static void test_urlEdgeCases( void** testFixtureState )
{
    ELASTIC_APM_UNUSED( testFixtureState );

    for ( const char* url : { (const char*)nullptr, "", "h", "https", "http", "httpx://host" } )
    {
        ProxyEnvSnapshot s = resolve( url, { { "http_proxy", "http://p:1" }, { "https_proxy", "http://s:2" } } );
        ASSERT_STR_EQ( "http://p:1", s.proxy.c_str() );
    }
}

int run_proxy_env_snapshot_tests()
{
    const struct CMUnitTest tests [] =
    {
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_noEnv ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_httpUsesLowercaseOnly ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_httpsLowerThenUpper ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_allProxyFallback ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_emptyValueIsTreatedAsNotSet ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_noProxy ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_longValuesAreNotTruncated ),
        ELASTIC_APM_CMOCKA_UNIT_TEST( test_urlEdgeCases ),
    };

    return cmocka_run_group_tests( tests, NULL, NULL );
}
