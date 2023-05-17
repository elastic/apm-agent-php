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

#include "basic_macros.h"

void gen_numbered_intercepting_callbacks_src(int numberedInterceptingCallbacksCount)
{
    // See "src/ext/numbered_intercepting_callbacks.h"

    ELASTIC_APM_FOR_EACH_INDEX_EX( unsigned int, i, numberedInterceptingCallbacksCount )
    {
        printf("ELASTIC_APM_DEFINE_NUMBERED_INTERCEPTING_CALLBACK( %u )""\n", i);
    }
    printf("\n");
    printf("enum { numberedInterceptingCallbacksCount = %u };""\n", numberedInterceptingCallbacksCount);
    printf("static zif_handler g_numberedInterceptingCallback[ numberedInterceptingCallbacksCount ] =\n");
    printf("{\n");
    ELASTIC_APM_FOR_EACH_INDEX_EX( unsigned int, i, numberedInterceptingCallbacksCount )
    {
        printf("\t""[ %u ] = ELASTIC_APM_NUMBERED_INTERCEPTING_CALLBACK_NAME( %u )", i, i);
        if ( i != numberedInterceptingCallbacksCount - 1 ) printf(",");
        printf("\n");
    }
    printf("};\n");
    printf("\n");
    printf("#undef ELASTIC_APM_DEFINE_NUMBERED_INTERCEPTING_CALLBACK""\n");
    printf("#undef ELASTIC_APM_NUMBERED_INTERCEPTING_CALLBACK_NAME""\n");
}