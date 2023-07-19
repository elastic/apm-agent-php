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

#include <stdbool.h>
#include "basic_macros.h"
#include "StringView.h"

//TODO bugprone-macro-parentheses - use std::span or any other container
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wunknown-pragmas"

#pragma clang diagnostic push
#pragma ide diagnostic ignored "bugprone-macro-parentheses"


#define ELASTIC_APM_DECLARE_ARRAY_VIEW( ValueType, ArrayViewType ) \
    struct ArrayViewType \
    { \
        size_t count; \
        ValueType* values; \
    }; \
    typedef struct ArrayViewType ArrayViewType

#pragma clang diagnostic pop

#pragma GCC diagnostic pop


ELASTIC_APM_DECLARE_ARRAY_VIEW( bool, BoolArrayView );
ELASTIC_APM_DECLARE_ARRAY_VIEW( int, IntArrayView );
ELASTIC_APM_DECLARE_ARRAY_VIEW( StringView, StringViewArrayView );
ELASTIC_APM_DECLARE_ARRAY_VIEW( Int64, Int64ArrayView );

#define ELASTIC_APM_MAKE_ARRAY_VIEW( ArrayViewType, countArg, valuesArg ) \
    ( (ArrayViewType){ .count = (countArg), .values = (valuesArg) } )

#define ELASTIC_APM_MAKE_EMPTY_ARRAY_VIEW( ArrayViewType ) \
    ( ELASTIC_APM_MAKE_ARRAY_VIEW( ArrayViewType, 0, NULL ) )

#define ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( ArrayViewType, staticArrayVar ) \
    ( ELASTIC_APM_MAKE_ARRAY_VIEW( ArrayViewType, ELASTIC_APM_STATIC_ARRAY_SIZE( (staticArrayVar) ), &((staticArrayVar)[0]) ) )
