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

#include <zend_ast.h>
#include "ConfigManager.h"
#include "StringView.h"
#include "TextOutputStream.h"
#include "ResultCode.h"

struct BoolArrayView
{
    unsigned int size;
    bool* values;
};
typedef struct BoolArrayView BoolArrayView;

#define ELASTIC_APM_MAKE_BOOL_ARRAY_VIEW( staticArrayVar ) \
    ( (BoolArrayView){ .size = ELASTIC_APM_STATIC_ARRAY_SIZE( (staticArrayVar) ), .values = &((staticArrayVar)[0]) } )

void elasticApmAstInstrumentationOnModuleInit( const ConfigSnapshot* config );
void elasticApmAstInstrumentationOnModuleShutdown();

void elasticApmAstInstrumentationOnRequestInit();
void elasticApmAstInstrumentationOnRequestShutdown();

String streamZendAstKind( zend_ast_kind kind, TextOutputStream* txtOutStream );

bool getAstGlobalName( zend_ast* astGlobal, /* out */ StringView* name );
bool getAstFunctionName( zend_ast* astFunction, /* out */ StringView* name );
zend_ast* insertAstFunctionPreHook( zend_ast* funcDeclAst, StringView className, StringView methodName, BoolArrayView shouldPassParameterByRef );
