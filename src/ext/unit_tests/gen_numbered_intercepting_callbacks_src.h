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

#pragma once

#include "basic_macros.h"

void gen_numbered_intercepting_callbacks_src(int numberedInterceptingCallbacksCount)
{
    // See "src/ext/numbered_intercepting_callbacks.h"

    ELASTICAPM_FOR_EACH_INDEX_EX( unsigned int, i, numberedInterceptingCallbacksCount )
    {
        printf("ELASTICAPM_DEFINE_NUMBERED_INTERCEPTING_CALLBACK( %u )""\n", i);
    }
    printf("\n");
    printf("enum { numberedInterceptingCallbacksCount = %u };""\n", numberedInterceptingCallbacksCount);
    printf("static zif_handler g_numberedInterceptingCallback[ numberedInterceptingCallbacksCount ] =\n");
    printf("{\n");
    ELASTICAPM_FOR_EACH_INDEX_EX( unsigned int, i, numberedInterceptingCallbacksCount )
    {
        printf("\t""[ %u ] = ELASTICAPM_NUMBERED_INTERCEPTING_CALLBACK_NAME( %u )", i, i);
        if ( i != numberedInterceptingCallbacksCount - 1 ) printf(",");
        printf("\n");
    }
    printf("};\n");
    printf("\n");
    printf("#undef ELASTICAPM_DEFINE_NUMBERED_INTERCEPTING_CALLBACK""\n");
    printf("#undef ELASTICAPM_NUMBERED_INTERCEPTING_CALLBACK_NAME""\n");
}