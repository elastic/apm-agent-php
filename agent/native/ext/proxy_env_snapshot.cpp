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
#include <initializer_list>
#include <ctype.h>

namespace
{
bool startsWithHttpsScheme( const char* url )
{
    if ( url == nullptr )
    {
        return false;
    }
    const char scheme[] = "https:";
    for ( size_t i = 0; scheme[ i ] != '\0'; ++i )
    {
        if ( url[ i ] == '\0' || tolower( static_cast< unsigned char >( url[ i ] ) ) != scheme[ i ] )
        {
            return false;
        }
    }
    return true;
}

// Returns the value of the first variable that is set to a non-empty string (same as libcurl does) or empty string
std::string firstNonEmptyEnvVar( GetEnvFunc getEnv, std::initializer_list< const char* > names )
{
    for ( const char* name : names )
    {
        const char* value = getEnv( name );
        if ( value != nullptr && value[ 0 ] != '\0' )
        {
            return std::string( value );
        }
    }
    return std::string();
}
} // namespace

ProxyEnvSnapshot resolveProxyEnvSnapshot( const char* serverUrl, GetEnvFunc getEnv )
{
    ProxyEnvSnapshot result;

    if ( startsWithHttpsScheme( serverUrl ) )
    {
        result.proxy = firstNonEmptyEnvVar( getEnv, { "https_proxy", "HTTPS_PROXY", "all_proxy", "ALL_PROXY" } );
    }
    else
    {
        // HTTP_PROXY is deliberately not used (httpoxy)
        result.proxy = firstNonEmptyEnvVar( getEnv, { "http_proxy", "all_proxy", "ALL_PROXY" } );
    }

    result.noProxy = firstNonEmptyEnvVar( getEnv, { "no_proxy", "NO_PROXY" } );

    return result;
}
