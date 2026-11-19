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
#include "ConfigSnapshot_forward_decl.h"
#include "StringView.h"
#include "TextOutputStream.h"
#include "ResultCode.h"
#include "ArrayView.h"

enum ArgCaptureSpec
{
    captureArgByValue,
    captureArgByRef,
    dontCaptureArg
};
typedef enum ArgCaptureSpec ArgCaptureSpec;
ELASTIC_APM_DECLARE_ARRAY_VIEW( ArgCaptureSpec, ArgCaptureSpecArrayView );

void astInstrumentationOnModuleInit( const ConfigSnapshot* config );
void astInstrumentationOnModuleShutdown();

void astInstrumentationOnRequestInit( const ConfigSnapshot* config );
void astInstrumentationOnRequestShutdown();

zend_ast_decl** findChildSlotForStandaloneFunctionAst( zend_ast* rootAst, StringView nameSpace, StringView funcName, size_t minParamsCount );
zend_ast_decl* findClassAst( zend_ast* rootAst, StringView nameSpace, StringView className );
zend_ast_decl** findChildSlotForMethodAst( zend_ast_decl* astClass, StringView methodName, size_t minParamsCount );

ResultCode insertAstForFunctionPreHook( zend_ast_decl *parentClass, zend_ast_decl* funcAstDecl, ArgCaptureSpecArrayView argCaptureSpecs );
ResultCode appendDirectCallToInstrumentation( zend_ast_decl** pAstChildSlot, StringView constNameForMethodName );
ResultCode wrapStandaloneFunctionAstWithPrePostHooks( zend_ast_decl** pAstChildSlot );

String streamZendAstKind( zend_ast_kind kind, TextOutputStream* txtOutStream );
