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

#include "ResultCode.h"
#include "ConfigManager.h"

ResultCode bootstrapTracerPhpPart( const ConfigSnapshot* config, const TimePoint* requestInitStartTime );

void shutdownTracerPhpPart( const ConfigSnapshot* config );

bool tracerPhpPartInterceptedCallPreHook( uint32_t interceptRegistrationId, zend_execute_data* execute_data );

void tracerPhpPartInterceptedCallPostHook( uint32_t dbgInterceptRegistrationId, zval* interceptedCallRetValOrThrown );

ResultCode onPhpErrorToTracerPhpPart( int type, const char* fileName, uint32_t lineNumber, const char* message );

ResultCode setLastThrownToTracerPhpPart( zval* thrown );
