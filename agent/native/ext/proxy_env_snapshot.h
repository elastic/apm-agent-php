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

#pragma once

#include <string>

/**
 * Proxy related environment variables resolved the same way libcurl does it.
 *
 * libcurl reads them with getenv() on every transfer unless CURLOPT_PROXY and CURLOPT_NOPROXY are set explicitly.
 * getenv() is not thread-safe against concurrent putenv()/setenv()/unsetenv() which PHP code can call at any time,
 * so libcurl running on the background sender thread must not call it for the proxy settings. Instead, the values are resolved once on the PHP thread,
 * before the sender thread is created, and passed to libcurl explicitly (empty string means "not set").
 */
struct ProxyEnvSnapshot
{
    std::string proxy;
    std::string noProxy;
};

typedef const char* (* GetEnvFunc )( const char* name );

/**
 * Lookup order mirrors libcurl's detect_proxy():
 *      "<scheme>_proxy", then "<SCHEME>_PROXY" (except for http - HTTP_PROXY is never used to protect from httpoxy),
 *      then "all_proxy", then "ALL_PROXY".
 * For no proxy list: "no_proxy", then "NO_PROXY".
 * Variables that are set to an empty string are treated as not set.
 *
 * @param serverUrl APM Server URL, its scheme selects the variable ("https" or anything else as "http")
 */
ProxyEnvSnapshot resolveProxyEnvSnapshot( const char* serverUrl, GetEnvFunc getEnv );
