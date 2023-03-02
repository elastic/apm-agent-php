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

#include "WordPress_instrumentation.h"
#include "log.h"
#include "AST_instrumentation.h"
#include "util.h"
#include "TextOutputStream.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_AUTO_INSTRUMENT

typedef bool (* FunctionToInstrumentPreconditionPredicate )();

struct FunctionToInstrument
{
    StringView funcName;
    BoolArrayView shouldPassParameterByRef;
    FunctionToInstrumentPreconditionPredicate preconditionPredicate;
};
typedef struct FunctionToInstrument FunctionToInstrument;

static FunctionToInstrument makeFunctionToInstrument( StringView funcName, BoolArrayView shouldPassParameterByRef, FunctionToInstrumentPreconditionPredicate preconditionPredicate )
{
    return (FunctionToInstrument) { .funcName = funcName, .shouldPassParameterByRef = shouldPassParameterByRef, .preconditionPredicate = preconditionPredicate };
}

#define ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( funcName, shouldPassParameterByRefStaticArrayVar, preconditionPredicate ) \
    ( \
    makeFunctionToInstrument \
    ( \
        ELASTIC_APM_STRING_LITERAL_TO_VIEW( (funcName) ), \
        ELASTIC_APM_MAKE_BOOL_ARRAY_VIEW( (shouldPassParameterByRefStaticArrayVar) ), \
        preconditionPredicate \
    ) \
    )

enum { numberOfFunctionsToInstrument = 5 };
static FunctionToInstrument g_functionsToInstrument[ numberOfFunctionsToInstrument ];

bool add_remove_filter_preconditionPredicate();

void wordPressInstrumentationOnModuleInit()
{
    static bool doNotPassAnyParameters[] = {};
    static bool passFirstParameterByValue[] = { false };
    static bool add_remove_filter_shouldPassParameterByRef[] = { false, true };

    FunctionToInstrument functionsToInstrument[ numberOfFunctionsToInstrument ] =
    {
        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "wp_plugin_directory_constants", doNotPassAnyParameters, /* preconditionPredicate: */ NULL ),
        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "do_action", passFirstParameterByValue, add_remove_filter_preconditionPredicate ),

//        // function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 )
//        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "add_filter", add_remove_filter_shouldPassParameterByRef, add_remove_filter_preconditionPredicate ),
//        // function remove_filter( $hook_name, $callback, $priority = 10 )
//        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "remove_filter", add_remove_filter_shouldPassParameterByRef, add_remove_filter_preconditionPredicate ),

        // function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 )
        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "add_action", add_remove_filter_shouldPassParameterByRef, add_remove_filter_preconditionPredicate ),
        // function remove_action( $hook_name, $callback, $priority = 10 )
        ELASTIC_APM_MAKE_FUNCTION_TO_INSTRUMENT( "remove_action", add_remove_filter_shouldPassParameterByRef, add_remove_filter_preconditionPredicate ),
    };

    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfFunctionsToInstrument )
    {
        g_functionsToInstrument[ i ] = functionsToInstrument[ i ];
    }
}

void wordPressInstrumentationOnModuleShutdown()
{
}

struct WordPressInstrumentationRequestScopedState
{
    bool seen_wp_filter_global_var_decl;
    bool seenFunctionToInstrument[ numberOfFunctionsToInstrument ];
    int numberOfFunctionsToInstrumentSeen;
};
typedef struct WordPressInstrumentationRequestScopedState WordPressInstrumentationRequestScopedState;

static WordPressInstrumentationRequestScopedState g_wordPressInstrumentationRequestScopedState;

void wordPressInstrumentationOnRequestInit()
{
    g_wordPressInstrumentationRequestScopedState.seen_wp_filter_global_var_decl = false;

    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfFunctionsToInstrument )
    {
        g_wordPressInstrumentationRequestScopedState.seenFunctionToInstrument[ i ] = false;
    }
    g_wordPressInstrumentationRequestScopedState.numberOfFunctionsToInstrumentSeen = 0;
}

void wordPressInstrumentationOnRequestShutdown()
{
}

void wordPressInstrumentationOnAstGlobal( zend_ast* astGlobal )
{
    if ( g_wordPressInstrumentationRequestScopedState.seen_wp_filter_global_var_decl )
    {
        return;
    }

    StringView globalVarName;
    if ( ! getAstGlobalName( astGlobal, &globalVarName ) )
    {
        return;
    }

    if ( areStringViewsEqual( globalVarName, ELASTIC_APM_STRING_LITERAL_TO_VIEW( "wp_filter" ) ) )
    {
        g_wordPressInstrumentationRequestScopedState.seen_wp_filter_global_var_decl = true;
        ELASTIC_APM_LOG_DEBUG( "Encountered wp_filter global variable declaration" );
    }
}

bool add_remove_filter_preconditionPredicate()
{
    return g_wordPressInstrumentationRequestScopedState.seen_wp_filter_global_var_decl;
}

zend_ast* wordPressInstrumentationOnAstFunction( zend_ast* funcDeclAst )
{
    WordPressInstrumentationRequestScopedState* requestScopedState = &g_wordPressInstrumentationRequestScopedState;

    if ( requestScopedState->numberOfFunctionsToInstrumentSeen == numberOfFunctionsToInstrument )
    {
        return funcDeclAst;
    }

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( funcDeclAst->kind == ZEND_AST_FUNC_DECL, "funcDeclAst->kind: %s", streamZendAstKind( funcDeclAst->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    StringView funcName;
    if ( ! getAstFunctionName( funcDeclAst, &funcName ) )
    {
        return funcDeclAst;
    }

    size_t functionToInstrumentIndex = numberOfFunctionsToInstrument;
    ELASTIC_APM_FOR_EACH_INDEX( i, numberOfFunctionsToInstrument )
    {
        if ( requestScopedState->seenFunctionToInstrument[ i ] )
        {
            continue;
        }
        if ( areStringViewsEqual( funcName, g_functionsToInstrument[ i ].funcName ) )
        {
            functionToInstrumentIndex = i;
            break;
        }
    }
    if ( functionToInstrumentIndex == numberOfFunctionsToInstrument )
    {
        return funcDeclAst;
    }
    requestScopedState->seenFunctionToInstrument[ functionToInstrumentIndex ] = true;

    FunctionToInstrumentPreconditionPredicate preconditionPredicate = g_functionsToInstrument[ functionToInstrumentIndex ].preconditionPredicate;
    if ( ( preconditionPredicate != NULL ) && ( ! preconditionPredicate() ) )
    {
        ELASTIC_APM_LOG_DEBUG( "Encountered function `%s' but precondition to instrument it is NOT satisfied - function will NOT be instrumented"
                               , streamStringView( funcName, &txtOutStream ) );
        return funcDeclAst;
    }

    ELASTIC_APM_LOG_DEBUG( "Instrumenting `%s' function...", streamStringView( funcName, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );
    return insertAstFunctionPreHook(
            funcDeclAst, ELASTIC_APM_STRING_LITERAL_TO_VIEW( "Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade" ), ELASTIC_APM_STRING_LITERAL_TO_VIEW( "onWordPressFunctionPreHook" ),
            g_functionsToInstrument[ functionToInstrumentIndex ].shouldPassParameterByRef );
}
