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

#include "AST_instrumentation.h"
#include "log.h"
#include <php_version.h>
#include <zend_ast.h>
#include <zend_compile.h>
#include <zend_arena.h>
#include "util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_AUTO_INSTRUMENT

zend_ast_process_t original_zend_ast_process;

#define ZEND_AST_ALLOC( size ) zend_arena_alloc(&CG(ast_arena), size);

String zendAstKindToString( zend_ast_kind kind )
{
#   define ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( enumMember ) \
        case enumMember: \
            return #enumMember; \
    /**/

    switch ( kind )
    {
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ZVAL )
        #ifdef ZEND_AST_CONSTANT
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONSTANT )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ZNODE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FUNC_DECL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLOSURE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS )
        #ifdef ZEND_AST_ARROW_FUNC
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARROW_FUNC )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARG_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARRAY )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ENCAPS_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EXPR_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STMT_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_IF )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CATCH_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PARAM_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLOSURE_USES )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_DECL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_DECL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST_DECL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAME_LIST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_ADAPTATIONS )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MAGIC_CONST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE )
        #ifdef ZEND_AST_CONSTANT_CLASS
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONSTANT_CLASS )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_VAR )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNPACK )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_PLUS )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_MINUS )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CAST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EMPTY )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ISSET )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SILENCE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SHELL_EXEC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLONE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EXIT )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRINT )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_INCLUDE_OR_EVAL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_OP )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRE_INC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRE_DEC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_POST_INC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_POST_DEC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_YIELD_FROM )
        #ifdef ZEND_AST_CLASS_NAME
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_NAME )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GLOBAL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNSET )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_RETURN )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_LABEL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_REF )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_HALT_COMPILER )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ECHO )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_THROW )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GOTO )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_BREAK )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONTINUE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DIM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC_PROP )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CALL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_REF )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_OP )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_BINARY_OP )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GREATER )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GREATER_EQUAL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_AND )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_OR )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARRAY_ELEM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NEW )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_INSTANCEOF )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_YIELD )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_COALESCE )
        #ifdef ZEND_AST_ASSIGN_COALESCE
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_COALESCE )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_WHILE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DO_WHILE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_IF_ELEM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH_CASE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DECLARE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE_TRAIT )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_PRECEDENCE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD_REFERENCE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAMESPACE )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE_ELEM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_ALIAS )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GROUP_USE )
        #ifdef ZEND_AST_PROP_GROUP
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_GROUP )
        #endif
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD_CALL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC_CALL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONDITIONAL )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRY )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CATCH )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PARAM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_ELEM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_ELEM )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FOR )
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FOREACH )

        default:
            return "UNKNOWN";
    }
#   undef ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE
}

static inline
size_t calcAstListAllocSize( uint32_t children )
{
    return sizeof( zend_ast_list ) - sizeof( zend_ast* ) + sizeof( zend_ast* ) * children;
}

static
zend_ast* createZvalAst( zval* zv, uint32_t attr, uint32_t lineno )
{
    #if PHP_VERSION_ID >= 70300 /* if PHP version is 7.3.0 and later */
    zend_ast* ast = zend_ast_create_zval_with_lineno( zv, lineno );
    ast->attr = attr;
    #else
    zend_ast* ast = zend_ast_create_zval_with_lineno( zv, attr, lineno );
    #endif

    return ast;
}

static
zend_ast* createStringAst( char* str, size_t len, uint32_t attr, uint32_t lineno )
{
    zval zv;
    zend_ast* ast;
    ZVAL_NEW_STR( &zv, zend_string_init( str, len, 0 ) );
    ast = createZvalAst( &zv, attr, /* lineno */ 0 );
    return ast;
}

static
zend_ast* createCatchTypeAst( uint32_t lineno )
{
    zend_ast * name = createStringAst( "Throwable", sizeof( "Throwable" ) - 1, ZEND_NAME_FQ, lineno );
    return zend_ast_create_list( 1, ZEND_AST_NAME_LIST, name );
}

static
zend_ast* createCatchAst( uint32_t lineno )
{
    zend_ast * exVarNameAst = createStringAst( "ex", sizeof( "ex" ) - 1, /* attr: */ 0, lineno );
    zend_ast * catchTypeAst = createCatchTypeAst( lineno );

    zend_ast * instrumentationPostHookCallAst =
            zend_ast_create( ZEND_AST_CALL
                             , createStringAst( "instrumentationPostHookException", sizeof( "instrumentationPostHookException" ) - 1, ZEND_NAME_FQ, lineno )
                             , zend_ast_create_list( 1
                                                     , ZEND_AST_ARG_LIST
                                                     , zend_ast_create( ZEND_AST_VAR
                                                                        , createStringAst( "ex", sizeof( "ex" ) - 1, /* attr: */ 0, lineno ) )
            ) );

    zend_ast * throwAst = zend_ast_create( ZEND_AST_THROW, instrumentationPostHookCallAst );
    throwAst->lineno = lineno;
    zend_ast * throwStmtListAst = zend_ast_create_list( 1, ZEND_AST_STMT_LIST, throwAst );
    throwStmtListAst->lineno = lineno;
    zend_ast * catchAst = zend_ast_create_list( 1
                                                , ZEND_AST_CATCH_LIST
                                                , zend_ast_create( ZEND_AST_CATCH
                                                                   , catchTypeAst
                                                                   , exVarNameAst
                                                                   , throwStmtListAst ) );
    catchAst->lineno = lineno;
    return catchAst;
}

struct TransformContext
{
    bool isInsideFunction;
    bool isFunctionRetByRef;
};
typedef struct TransformContext TransformContext;

TransformContext g_transformContext = { .isInsideFunction = false, .isFunctionRetByRef = false };

TransformContext makeTransformContext( TransformContext* base, bool isInsideFunction, bool isFunctionRetByRef )
{
    TransformContext transformCtx = *base;
    transformCtx.isInsideFunction = isInsideFunction;
    transformCtx.isFunctionRetByRef = isFunctionRetByRef;
    return transformCtx;
}

static
zend_ast* transformAst( zend_ast* ast, int nestingDepth );

static
zend_ast* transformFunctionAst( zend_ast* originalAst, int nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( originalAst->kind ) );

    zend_ast * transformedAst;
    zend_ast_decl* funcDeclAst = (zend_ast_decl*) originalAst;

    TransformContext savedTransformCtx = makeTransformContext(
            &g_transformContext
            , /* isInsideFunction: */ true
            , /* isFunctionRetByRef */ funcDeclAst->flags & ZEND_ACC_RETURN_REFERENCE );

    if ( ! isStringViewPrefixIgnoringCase( stringToView( ZSTR_VAL( funcDeclAst->name ) )
                                           , ELASTIC_APM_STRING_LITERAL_TO_VIEW( "functionToInstrument" ) ) )
    {
        transformedAst = originalAst;
        goto finally;
    }

    zend_ast_list* funcStmtListAst = zend_ast_get_list( funcDeclAst->child[ 2 ] );

    // guess at feasible line numbers
    uint32_t funcStmtBeginLineNumber = funcStmtListAst->lineno;
    uint32_t funcStmtEndLineNumber = funcStmtListAst->child[ funcStmtListAst->children - 1 ]->lineno;

    zend_ast * callInstrumentationPreHookAst =
            zend_ast_create(
                    ZEND_AST_CALL
                    , createStringAst( "instrumentationPreHook", sizeof( "instrumentationPreHook" ) - 1, ZEND_NAME_FQ, originalAst->lineno )
                    , zend_ast_create_list( 1
                                            , ZEND_AST_ARG_LIST
                                            , zend_ast_create( ZEND_AST_CALL
                                                               , createStringAst( "func_get_args", sizeof( "func_get_args" ) - 1, ZEND_NAME_FQ, originalAst->lineno )
                                                               , zend_ast_create_list( 0, ZEND_AST_ARG_LIST ) )
            ) );

    zend_ast * callInstrumentationPostHookAst =
            zend_ast_create(
                    ZEND_AST_CALL
                    , createStringAst( "instrumentationPostHookRetVoid", sizeof( "instrumentationPostHookRetVoid" ) - 1, ZEND_NAME_FQ, originalAst->lineno )
                    , zend_ast_create_list( 0, ZEND_AST_ARG_LIST ) );

    zend_ast * catchAst = createCatchAst( funcStmtEndLineNumber );
    zend_ast * finallyAst = NULL;

    zend_ast * tryCatchAst = zend_ast_create( ZEND_AST_TRY, transformAst( funcDeclAst->child[ 2 ], nestingDepth + 1 ), catchAst, finallyAst );
    tryCatchAst->lineno = funcStmtBeginLineNumber;
    zend_ast_list* newFuncBodyAst = ZEND_AST_ALLOC( calcAstListAllocSize( 3 ) );
    newFuncBodyAst->kind = ZEND_AST_STMT_LIST;
    newFuncBodyAst->lineno = funcStmtBeginLineNumber;
    newFuncBodyAst->children = 3;
    newFuncBodyAst->child[ 0 ] = callInstrumentationPreHookAst;
    newFuncBodyAst->child[ 1 ] = tryCatchAst;
    newFuncBodyAst->child[ 2 ] = callInstrumentationPostHookAst;
    funcDeclAst->child[ 2 ] = (zend_ast*) newFuncBodyAst;
    transformedAst = originalAst;

    finally:
    g_transformContext = savedTransformCtx;
    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( transformedAst->kind ) );
    return transformedAst;
}

static
zend_ast* transformReturnAst( zend_ast* originalAst, int nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( originalAst->kind ) );

    zend_ast * transformedAst;

    // if there isn't an active function then don't wrap it
    // e.g. return at file scope
    if ( ! g_transformContext.isInsideFunction )
    {
        transformedAst = originalAst;
        goto finally;
    }

    zend_ast * returnExprAst = originalAst->child[ 0 ];
    // If it's an empty return;
    if ( returnExprAst == NULL )
    {
        zend_ast * callInstrumentationPostHookAst = zend_ast_create(
                ZEND_AST_CALL
                , createStringAst( "instrumentationPostHookRetVoid", sizeof( "instrumentationPostHookRetVoid" ) - 1, ZEND_NAME_FQ, originalAst->lineno )
                , zend_ast_create_list( 0, ZEND_AST_ARG_LIST ) );
        transformedAst = zend_ast_create_list( 2, ZEND_AST_STMT_LIST, callInstrumentationPostHookAst, originalAst );
        goto finally;
    }

    // Either: return by reference or not
    char* name;
    size_t len;
    if ( g_transformContext.isFunctionRetByRef )
    {
        name = "instrumentationPostHookRetByRef";
        len = sizeof( "instrumentationPostHookRetByRef" ) - 1;
    }
    else
    {
        name = "instrumentationPostHookRetNotByRef";
        len = sizeof( "instrumentationPostHookRetNotByRef" ) - 1;
    }
    zend_ast * callInstrumentationPostHookAst = zend_ast_create(
            ZEND_AST_CALL
            , createStringAst( name, len, ZEND_NAME_FQ, originalAst->lineno )
            , zend_ast_create_list( 1, ZEND_AST_ARG_LIST, returnExprAst ) );
    originalAst->child[ 0 ] = callInstrumentationPostHookAst;
    transformedAst = originalAst;

    finally:
    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( transformedAst->kind ) );
    return transformedAst;
}

static
zend_ast* transformChildrenAst( zend_ast* ast, int nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( ast->kind ) );

    zend_ast * transformedAst = ast;

    uint32_t childrenCount = zend_ast_get_num_children( ast );
    ELASTIC_APM_FOR_EACH_INDEX( i, childrenCount )
    {
        if ( ast->child[ i ] != NULL )
        {
            ast->child[ i ] = transformAst( ast->child[ i ], nestingDepth + 1 );
        }
    }

    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( transformedAst->kind ) );
    return transformedAst;
}

static
zend_ast* transformAst( zend_ast* ast, int nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( ast->kind ) );

    zend_ast * transformedAst;

    if ( zend_ast_is_list( ast ) )
    {
        zend_ast_list* list = zend_ast_get_list( ast );
        uint32_t i;
        for ( i = 0 ; i < list->children ; i ++ )
        {
            if ( list->child[ i ] )
            {
                list->child[ i ] = transformAst( list->child[ i ], nestingDepth + 1 );
            }
        }
        transformedAst = ast;
        goto finally;
    }

    switch ( ast->kind )
    {
        case ZEND_AST_ZVAL:
        #ifdef ZEND_AST_CONSTANT
        case ZEND_AST_CONSTANT:
        #endif
            transformedAst = ast;
            goto finally;

        case ZEND_AST_FUNC_DECL:
        case ZEND_AST_METHOD:
            transformedAst = transformFunctionAst( ast, nestingDepth + 1 );
            goto finally;

        case ZEND_AST_RETURN:
            transformedAst = transformReturnAst( ast, nestingDepth + 1 );
            goto finally;

        default:
            transformedAst = transformChildrenAst( ast, nestingDepth + 1 );
            goto finally;
    }

    finally:
    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "%s: kind: %s", streamIndent( nestingDepth, &txtOutStream ), zendAstKindToString( transformedAst->kind ) );
    return transformedAst;
}

static
void elasticApmProcessAstRoot( zend_ast* ast )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "ast->kind: %s", zendAstKindToString( ast->kind ) );

    zend_ast * transformedAst = transformAst( ast, 0 );
    if ( original_zend_ast_process != NULL ) original_zend_ast_process( transformedAst );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void astInstrumentationInit()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    original_zend_ast_process = zend_ast_process;
    zend_ast_process = elasticApmProcessAstRoot;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void astInstrumentationShutdown()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    zend_ast_process = original_zend_ast_process;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}
