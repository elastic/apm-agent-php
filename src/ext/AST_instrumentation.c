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
#include <zend_API.h>
#include "util.h"
#include "util_for_PHP.h"
#include "StringView.h"
#include "WordPress_instrumentation.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_AUTO_INSTRUMENT

static bool isOriginalZendAstProcessSet = false;
static zend_ast_process_t originalZendAstProcess = NULL;

//#define ZEND_AST_ALLOC( size ) zend_arena_alloc(&CG(ast_arena), size);

static bool isLoadingAgentPhpCode = false;

void elasticApmBeforeLoadingAgentPhpCode()
{
    isLoadingAgentPhpCode = true;
}

void elasticApmAfterLoadingAgentPhpCode()
{
    isLoadingAgentPhpCode = false;
}

String zendAstKindToString( zend_ast_kind kind )
{
#   define ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( enumMember ) \
        case enumMember: \
            return #enumMember; \
    /**/

    // Up to date with  PHP v8.2.3
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
        #ifdef ZEND_AST_ATTRIBUTE
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE )
        #endif
        #ifdef ZEND_AST_ATTRIBUTE_GROUP
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE_GROUP )
        #endif
        #ifdef ZEND_AST_ATTRIBUTE_LIST
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE_LIST )
        #endif
        #ifdef ZEND_AST_CALLABLE_CONVERT
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CALLABLE_CONVERT )
        #endif
        #ifdef ZEND_AST_CLASS_CONST_GROUP
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST_GROUP )
        #endif
        #ifdef ZEND_AST_CONST_ENUM_INIT
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_ENUM_INIT )
        #endif
        #ifdef ZEND_AST_ENUM_CASE
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ENUM_CASE )
        #endif
        #ifdef ZEND_AST_MATCH
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH )
        #endif
        #ifdef ZEND_AST_MATCH_ARM
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH_ARM )
        #endif
        #ifdef ZEND_AST_MATCH_ARM_LIST
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH_ARM_LIST )
        #endif
        #ifdef ZEND_AST_NAMED_ARG
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAMED_ARG )
        #endif
        #ifdef ZEND_AST_NULLSAFE_METHOD_CALL
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NULLSAFE_METHOD_CALL )
        #endif
        #ifdef ZEND_AST_NULLSAFE_PROP
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NULLSAFE_PROP )
        #endif
        #ifdef ZEND_AST_TYPE_INTERSECTION
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE_INTERSECTION )
        #endif
        #ifdef ZEND_AST_TYPE_UNION
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE_UNION )
        #endif

        default:
            return "UNKNOWN";
    }
#   undef ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE
}

String streamZendAstKind( zend_ast_kind kind, TextOutputStream* txtOutStream )
{
    streamPrintf( txtOutStream, "%s (%d)", zendAstKindToString( kind ), (int)kind );
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

zend_ast* transformAst( zend_ast* inAst, int nestingDepth );

void visitAstGlobal( zend_ast* astGlobal )
{
    wordPressInstrumentationOnAstGlobal( astGlobal );
}

bool getStringFromAstZval( zend_ast* astZval, /* out */ StringView* pResult )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( astZval->kind != ZEND_AST_ZVAL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astZval->kind: %s", streamZendAstKind( astZval->kind, &txtOutStream ) );
        return false;
    }

    zval* zVal = zend_ast_get_zval( astZval );
    if ( zVal == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - zVal == NULL" );
        return false;
    }
    int zValType = (int)Z_TYPE_P( zVal );
    if ( zValType != IS_STRING )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - zValType: %s (%d)", zend_get_type_by_const( zValType ), (int)zValType );
        return false;
    }

    zend_string* zString = Z_STR_P( zVal );
    *pResult = zStringToStringView( zString );
    ELASTIC_APM_LOG_TRACE( "Returning true - with result string [length: %"PRIu64"]: %.*s", (UInt64)(pResult->length), (int)(pResult->length), pResult->begin );
    return true;
}

bool getAstGlobalName( zend_ast* astGlobal, /* out */ StringView* name )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( astGlobal->kind == ZEND_AST_GLOBAL, "astGlobal->kind: %s", streamZendAstKind( astGlobal->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    uint32_t astGlobalChildrenCount = zend_ast_get_num_children( astGlobal );
    if ( astGlobalChildrenCount < 1 )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astGlobalChildrenCount: %d", (int)astGlobalChildrenCount );
        return false;
    }

    zend_ast* astGlobalChild = astGlobal->child[ 0 ];
    if ( astGlobalChild == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astGlobalChild == NULL" );
        return false;
    }
    if ( astGlobalChild->kind != ZEND_AST_VAR )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astGlobalChild->kind: %s", streamZendAstKind( astGlobalChild->kind, &txtOutStream ) );
        return false;
    }
    uint32_t astGlobalChildChildrenCount = zend_ast_get_num_children( astGlobalChild );
    if ( astGlobalChildChildrenCount < 1 )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astGlobalChildChildrenCount: %d", (int)astGlobalChildChildrenCount );
        return false;
    }

    zend_ast* astGlobalGrandChild = astGlobalChild->child[ 0 ];
    if ( astGlobalGrandChild == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astGlobalGrandChild == NULL" );
        return false;
    }

    return getStringFromAstZval( astGlobalGrandChild, /* out */ name );
}

bool getAstFunctionName( zend_ast* astFunction, /* out */ StringView* name )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( astFunction->kind == ZEND_AST_FUNC_DECL, "astFunction->kind: %s", streamZendAstKind( astFunction->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    zend_ast_decl* astFunctionAsDecl = (zend_ast_decl*)astFunction;

    if ( astFunctionAsDecl->name == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astFunctionAsDecl->name == NULL" );
        return false;
    }

    *name = zStringToStringView( astFunctionAsDecl->name );
    ELASTIC_APM_LOG_TRACE( "Returning true - name [length: %"PRIu64"]: %.*s", (UInt64)(name->length), (int)(name->length), name->begin );
    return true;
}

bool getAstFunctionParameterName( zend_ast* funcDeclAst, unsigned int parameterIndex, /* out */ StringView* name )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( funcDeclAst->kind == ZEND_AST_FUNC_DECL, "astFunc->kind: %s", streamZendAstKind( funcDeclAst->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    zend_ast_decl* astFuncAsDecl = (zend_ast_decl*)funcDeclAst;
    zend_ast* astFuncParams = astFuncAsDecl->child[ 0 ]; // function list of parameters is always child[ 0 ]
    if ( astFuncParams->kind != ZEND_AST_PARAM_LIST || ! zend_ast_is_list( astFuncParams )  )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - zend_ast_is_list( astFuncParams ): %s, astFuncParams->kind: %s"
                               , boolToString( zend_ast_is_list( astFuncParams ) ), streamZendAstKind( astFuncParams->kind, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        return false;
    }
    zend_ast_list* astFuncParamsAsList = zend_ast_get_list( astFuncParams );

    if ( parameterIndex >= astFuncParamsAsList->children )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astFuncParamsAsList->children: %d, parameterIndex: %d", (int)astFuncParamsAsList->children, (int)parameterIndex );
        return false;
    }

    zend_ast* param = astFuncParamsAsList->child[ parameterIndex ];
    if ( param == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - param == NULL" );
        return false;
    }

    if ( param->kind != ZEND_AST_PARAM )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - param->kind: %s", streamZendAstKind( param->kind, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        return false;
    }

    if ( zend_ast_get_num_children( param ) == 0 )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - zend_ast_get_num_children( param ): %d", (int)zend_ast_get_num_children( param ) );
        return false;
    }

    zend_ast* nameAst = param->child[ 1 ]; // parameter name is in child[ 1 ]->val
    if ( nameAst == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - nameAst == NULL" );
        return false;
    }

    return getStringFromAstZval( nameAst, /* out */ name );
}

void dbgDumpAst( zend_ast* ast, UInt32 nestingDepth );

void dbgDumpAstPrint( String text, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_TRACE( "%s%s", streamIndent( nestingDepth, &txtOutStream ), text );
}

void dbgDumpAstNodeData( zend_ast_kind kind, uint32_t lineNumber, zend_ast_attr attr, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    dbgDumpAstPrint( streamPrintf( &txtOutStream, "%s (line: %u, attr: %u)", streamZendAstKind( kind, &txtOutStream ), (unsigned)lineNumber, (unsigned)attr ), nestingDepth );
}

void dbgDumpAstNode( zend_ast* ast, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( ast == NULL )
    {
        dbgDumpAstPrint( "NULL", nestingDepth );
        return;
    }

    dbgDumpAstNodeData( ast->kind, zend_ast_get_lineno( ast ), ast->attr, nestingDepth );
}

String dbgDumpAstZvalStreamVal( zend_ast* ast, TextOutputStream* txtOutStream )
{
    zval* zVal = zend_ast_get_zval( ast );
    if ( zVal == NULL )
    {
        return "ast->val is NULL";
    }

    int zValType = (int)Z_TYPE_P( zVal );
    switch ( zValType )
    {
        case IS_STRING:
        {
            StringView strVw = zStringToStringView( Z_STR_P( zVal ) );
            return streamPrintf( txtOutStream, "type: string, value [length: %"PRIu64"]: %.*s", (UInt64)(strVw.length), (int)(strVw.length), strVw.begin );
        }

        case IS_LONG:
            return streamPrintf( txtOutStream, "type: long, value: %"PRId64, (Int64)(Z_LVAL_P( zVal )) );

        case IS_DOUBLE:
            return streamPrintf( txtOutStream, "type: double, value: %f", (double)(Z_DVAL_P( zVal )) );

        case IS_NULL:
            return streamPrintf( txtOutStream, "type: null" );

        case IS_FALSE:
            return streamPrintf( txtOutStream, "type: false" );
        case IS_TRUE:
            return streamPrintf( txtOutStream, "type: true " );

        default:
            return streamPrintf( txtOutStream, "type: %s (%d)", zend_get_type_by_const( zValType ), (int)zValType );
    }
}

void dbgDumpAstZval( zend_ast* ast, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    dbgDumpAstPrint(
            streamPrintf(
                    &txtOutStream
                    , "%s (line: %d, attr: %d) [%s]"
                    , streamZendAstKind( ast->kind, &txtOutStream )
                    , (int)zend_ast_get_lineno( ast )
                    , (int)(ast->attr)
                    , dbgDumpAstZvalStreamVal( ast, &txtOutStream )
            )
            , nestingDepth
    );
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void dbgDumpAstGenericWithChildren( zend_ast* ast, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    dbgDumpAstNode( ast, nestingDepth );

    ELASTIC_APM_FOR_EACH_INDEX( i, zend_ast_get_num_children( ast ) )
    {
        dbgDumpAst( ast->child[ i ], nestingDepth + 1 );
    }
}
#pragma clang diagnostic pop

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void dbgDumpAstList( zend_ast* ast, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    zend_ast_list* astAsList = zend_ast_get_list( ast );
    dbgDumpAstNodeData( astAsList->kind, astAsList->lineno, astAsList->attr, nestingDepth );

    ELASTIC_APM_FOR_EACH_INDEX( i, astAsList->children )
    {
        dbgDumpAst( astAsList->child[ i ], nestingDepth + 1 );
    }
}
#pragma clang diagnostic pop

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void dbgDumpAstDeclWithChildren( zend_ast* ast, UInt32 childrenCount, UInt32 nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    dbgDumpAstNode( ast, nestingDepth );

    zend_ast_decl* astAsDecl = (zend_ast_decl*)ast;
    ELASTIC_APM_FOR_EACH_INDEX( i, childrenCount )
    {
        dbgDumpAst( astAsDecl->child[ i ], nestingDepth + 1 );
    }
}
#pragma clang diagnostic pop

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void dbgDumpAst( zend_ast* ast, UInt32 nestingDepth )
{
    if ( ast == NULL )
    {
        dbgDumpAstNode( ast, nestingDepth );
        return;
    }

    if ( zend_ast_is_list( ast )  )
    {
        dbgDumpAstList( ast, nestingDepth );
        return;
    }

    switch ( ast->kind )
    {
        case ZEND_AST_FUNC_DECL:
            dbgDumpAstDeclWithChildren( ast, /* childrenCount */ 3, nestingDepth );
            break;

        case ZEND_AST_ARRAY_ELEM:
        case ZEND_AST_CALL:
        case ZEND_AST_GLOBAL:
        case ZEND_AST_METHOD_CALL:
        case ZEND_AST_PARAM:
        case ZEND_AST_STATIC_CALL:
        case ZEND_AST_VAR:
            dbgDumpAstGenericWithChildren( ast, nestingDepth );
            break;

        case ZEND_AST_ZVAL:
            dbgDumpAstZval( ast, nestingDepth );
            break;

        default:
            dbgDumpAstNode( ast, nestingDepth );
    }
}
#pragma clang diagnostic pop

void zendStringReleaseAndNull( zend_string** inOutPtr )
{
    if ( *inOutPtr != NULL )
    {
        zend_string_release( *inOutPtr );
        *inOutPtr = NULL;
    }
}

void destroyAstAndNull( zend_ast** inOutPtr )
{
    if ( *inOutPtr != NULL )
    {
        zend_ast_destroy( *inOutPtr );
        *inOutPtr = NULL;
    }
}

ResultCode createZString( StringView inStr, zend_string** pResult )
{
    zend_string* localResult = zend_string_init( inStr.begin, inStr.length, /* persistent: */ 0 );
    if ( localResult == NULL )
    {
        return resultOutOfMemory;
    }
    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    return resultSuccess;
}

ResultCode createAstWithOneChild( zend_ast_kind kind, zend_ast* child, zend_ast** pResult )
{
    ResultCode resultCode;
    zend_ast* localResult = NULL;

    localResult = zend_ast_create( kind, /* children: */ child );
    if ( localResult == NULL )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultOutOfMemory );
    }

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    goto finally;
}

ResultCode createAstWithAttributeAndTwoChildren( zend_ast_kind kind, zend_ast_attr attr, zend_ast* child1, zend_ast* child2, zend_ast** pResult )
{
    ResultCode resultCode;
    zend_ast* localResult = NULL;

    localResult = zend_ast_create_ex( kind, attr, /* children: */ child1, child2 );
    if ( localResult == NULL )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultOutOfMemory );
    }

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    goto finally;
}

ResultCode createAstWithThreeChildren( zend_ast_kind kind, zend_ast* child1, zend_ast* child2, zend_ast* child3, zend_ast** pResult )
{
    ResultCode resultCode;
    zend_ast* localResult = NULL;

    localResult = zend_ast_create( kind, /* children: */ child1, child2, child3 );
    if ( localResult == NULL )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultOutOfMemory );
    }

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    goto finally;
}

ResultCode createAstZVal( zval* zv, uint32_t lineNumber, zend_ast** pResult )
{
    #if PHP_VERSION_ID >= 70300 /* if PHP version is 7.3.0 and later */
    zend_ast* localResult = zend_ast_create_zval_with_lineno( zv, lineNumber );
    #else
    zend_ast* localResult = zend_ast_create_zval_with_lineno( zv, /* attr */ 0, lineNumber );
    #endif
    if ( localResult == NULL )
    {
        return resultOutOfMemory;
    }

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    return resultSuccess;
}

ResultCode createAstZValString( StringView inStr, uint32_t lineNumber, zend_ast** pResult )
{
    ResultCode resultCode;
    zend_string* asZString = NULL;
    zval stringAsZVal;
    zend_ast* localResult = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createZString( inStr, /* out */ &asZString ) );
    ZVAL_NEW_STR( &stringAsZVal, asZString );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstZVal( &stringAsZVal, lineNumber, /* out */ &localResult ) );
    asZString = NULL;

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    zendStringReleaseAndNull( &asZString );
    goto finally;
}

ResultCode createAstVar( StringView name, uint32_t lineNumber, zend_ast** pResult )
{
    //    ZEND_AST_VAR (256) (line: 121)
    //        ZEND_AST_ZVAL (64) (line: 483) [type: string, value [length: 9]: hook_name]

    ResultCode resultCode;
    zend_ast* nameAst = NULL;
    zend_ast* localResult = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstZValString( name, lineNumber, /* out */ &nameAst ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstWithOneChild( /* kind */ ZEND_AST_VAR, /* child */ nameAst, /* out */ &localResult ) );
    nameAst = NULL;

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    destroyAstAndNull( &nameAst );
    goto finally;
}

ResultCode createAstList( zend_ast_kind kind, uint32_t lineNumber, zend_ast** pResult )
{
    zend_ast* localResult = zend_ast_create_list( /* init_children */ 0, kind );
    if ( localResult == NULL )
    {
        return resultOutOfMemory;
    }
    ((zend_ast_list*)localResult)->lineno = lineNumber;
    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    return resultSuccess;
}

ResultCode addToAstList( zend_ast* newElement, zend_ast** pInSrcListOutNewList )
{
    zend_ast* localResult = zend_ast_list_add( /* in */ *pInSrcListOutNewList, newElement );
    if ( localResult == NULL )
    {
        return resultOutOfMemory;
    }
    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pInSrcListOutNewList );
    return resultSuccess;
}

ResultCode createAstArrayForParameters( zend_ast* funcDeclAst, BoolArrayView shouldPassParameterByRef, zend_ast** pResult )
{
    //                ZEND_AST_ARRAY (129) (line: 121, attr: 3) <- [$hook_name, /* ref */ &$callback] and 3 == ZEND_ARRAY_SYNTAX_SHORT
    //                    ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 0)
    //                        ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 9]: hook_name]
    //                        NULL
    //                    ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 1) <- attr == 1 because callback variable is taken by reference
    //                        ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 8]: callback]
    //                        NULL

    ResultCode resultCode;
    uint32_t lineNumber = zend_ast_get_lineno( funcDeclAst );
    zend_ast* varAst = NULL;
    zend_ast* arrayElementAst = NULL;
    zend_ast* localResult = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstList( ZEND_AST_ARRAY, lineNumber, /* out */ &localResult ) );
    ((zend_ast_list*)localResult)->attr = ZEND_ARRAY_SYNTAX_SHORT;

    ELASTIC_APM_FOR_EACH_INDEX( i, shouldPassParameterByRef.size )
    {
        StringView parameterName;
        // ZEND_AST_ARRAY_ELEM attribute should be 1 when passed by reference and 0 when passed by value
        zend_ast_attr arrayElemAttr = shouldPassParameterByRef.values[ i ] ? 1 : 0;
        if ( ! getAstFunctionParameterName( funcDeclAst, /* parameterIndex */ i, /* out */ &( parameterName ) ) )
        {
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
        ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstVar( parameterName, lineNumber, /* out */ &varAst ) );

        // Array element value is the first child (i.e., index 0) and array element key is the second child (i.e., index 1)
        ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstWithAttributeAndTwoChildren(
                ZEND_AST_ARRAY_ELEM
                , arrayElemAttr
                , /* array element value: */ varAst
                , /* array element key: */ NULL
                , /* out */ &arrayElementAst ) );
        varAst = NULL;

        ELASTIC_APM_CALL_IF_FAILED_GOTO( addToAstList( arrayElementAst, /* out */ &localResult ) );
        arrayElementAst = NULL;
    }

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    destroyAstAndNull( &arrayElementAst );
    destroyAstAndNull( &varAst );
    goto finally;
}

ResultCode createAstStaticCall( StringView className, StringView methodName, zend_ast* argList, uint32_t lineNumber, zend_ast** pResult )
{
    //        ZEND_AST_STATIC_CALL (770) (line: 121)
    //            ZEND_AST_ZVAL (64) (line: 0) [type: string, value [length: 60]: Elastic\Apm\Impl\AutoInstrument\WordPressAutoInstrumentation]
    //            ZEND_AST_ZVAL (64) (line: 483) [type: string, value [length: 29]: preprocessAddFilterParameters]
    //            ZEND_AST_ARG_LIST (128) (line: 121)

    ResultCode resultCode;
    zend_ast* classNameAst = NULL;
    zend_ast* methodNameAst = NULL;
    zend_ast* localResult = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstZValString( className, lineNumber, /* out */ &classNameAst ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstZValString( methodName, lineNumber, /* out */ &methodNameAst ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstWithThreeChildren( ZEND_AST_STATIC_CALL, classNameAst, methodNameAst, argList, /* out */ &localResult ) );
    classNameAst = NULL;
    methodNameAst = NULL;

    ELASTIC_APM_MOVE_PTR( /* in,out */ localResult, /* out */ *pResult );
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    destroyAstAndNull( &localResult );
    destroyAstAndNull( &methodNameAst );
    destroyAstAndNull( &classNameAst );
    goto finally;
}

zend_ast* insertAstFunctionPreHook( zend_ast* funcDeclAst, StringView className, StringView methodName, BoolArrayView shouldPassParameterByRef )
{
    //    ZEND_AST_STMT_LIST (132) (line: 121, attr: 0) <- new body
    //        ZEND_AST_STATIC_CALL (770) (line: 121, attr: 0)
    //            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 45]: Elastic\Apm\Impl\AutoInstrument\PhpPartFacade]
    //            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 26]: onWordPressFunctionPreHook]
    //            ZEND_AST_ARG_LIST (128) (line: 121, attr: 0)
    //                ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 10]: add_filter]
    //                ZEND_AST_ARRAY (129) (line: 121, attr: 3) <- [$hook_name, /* ref */ &$callback]
    //                    ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 0)
    //                        ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 9]: hook_name]
    //                        NULL
    //                    ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 1) <- &$callback
    //                        ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                            ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value [length: 8]: callback]
    //                        NULL

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY();
    dbgDumpAst( funcDeclAst, 0 );

    ResultCode resultCode;
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    uint32_t lineNumber = zend_ast_get_lineno( funcDeclAst );
    StringView funcName;
    zend_ast* originalFuncBody = NULL; // it's not created by this function, so we should NOT clean it on failure
    zend_ast* funcNameAst = NULL;
    zend_ast* arrayParametersAst = NULL;
    zend_ast* argListAst = NULL;
    zend_ast* callAst = NULL;
    zend_ast* newFuncBody = NULL;
    zend_ast** astsToCleanOnFailure[] = { &funcNameAst, &arrayParametersAst, &argListAst, &callAst, &newFuncBody };

    ELASTIC_APM_ASSERT( funcDeclAst->kind == ZEND_AST_FUNC_DECL, "funcDeclAst->kind: %s", streamZendAstKind( funcDeclAst->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    zend_ast_decl* funcDecl = (zend_ast_decl*)funcDeclAst;
    originalFuncBody = funcDecl->child[ 2 ]; // function body is always child[ 2 ]
    if ( originalFuncBody == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "originalFuncBody == NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    if ( originalFuncBody->kind != ZEND_AST_STMT_LIST )
    {
        ELASTIC_APM_LOG_TRACE( "originalFuncBody->kind: %s", streamZendAstKind( originalFuncBody->kind, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! getAstFunctionName( funcDeclAst, &funcName ) )
    {
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstZValString( funcName, lineNumber, /* out */ &funcNameAst ) );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstArrayForParameters( funcDeclAst, shouldPassParameterByRef, /* out */ &arrayParametersAst ) );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstList( ZEND_AST_ARG_LIST, lineNumber, /* out */ &argListAst ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToAstList( funcNameAst, /* in,out */ &argListAst ) );
    funcNameAst = NULL;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToAstList( arrayParametersAst, /* in,out */ &argListAst ) );
    arrayParametersAst = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstStaticCall( className, methodName, argListAst, lineNumber, /* out */ &callAst ) );
    argListAst = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstList( ZEND_AST_STMT_LIST, lineNumber, /* out */ &newFuncBody ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToAstList( callAst, /* in,out */ &newFuncBody ) );
    callAst = NULL;

    // Attaching originalFuncBody at the end of newFuncBody should be the last step that can fail
    // because on failure we will destroy newFuncBody which will recursively destroy all of its children,
    // but we should NOT destroy originalFuncBody because we didn't create it
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToAstList( originalFuncBody, /* in,out */ &newFuncBody ) );
    ELASTIC_APM_MOVE_PTR( /* in,out */ newFuncBody, /* out */ funcDecl->child[ 2 ] ); // function body is always child[ 2 ]
    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG();
    dbgDumpAst( funcDeclAst, 0 );
    return funcDeclAst;

    failure:
    ELASTIC_APM_FOR_EACH_INDEX( i, ELASTIC_APM_STATIC_ARRAY_SIZE( astsToCleanOnFailure ) )
    {
        destroyAstAndNull( astsToCleanOnFailure[ i ] );
    };
    goto finally;
}

zend_ast* transformAstFunction( zend_ast* inAst )
{
    zend_ast* outAst = inAst;

    outAst = wordPressInstrumentationOnAstFunction( outAst );

    return outAst;
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void transformChildrenAst( zend_ast* children[], uint32_t childrenCount, int nestingDepth )
{
    ELASTIC_APM_FOR_EACH_INDEX( i, childrenCount )
    {
        if ( children[ i ] != NULL )
        {
            children[ i ] = transformAst( children[ i ], nestingDepth + 1 );
        }
    }
}
#pragma clang diagnostic pop

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
zend_ast* transformAst( zend_ast* inAst, int nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_TRACE( "%s%s (line: %d)", streamIndent( nestingDepth, &txtOutStream ), streamZendAstKind( inAst->kind, &txtOutStream ), (int)zend_ast_get_lineno( inAst ) );
    textOutputStreamRewind( &txtOutStream );

    zend_ast* outAst = inAst;

    if ( zend_ast_is_list( inAst ) )
    {
        zend_ast_list* astAsList = zend_ast_get_list( inAst );
        transformChildrenAst( astAsList->child, /* childrenCount: */ astAsList->children, nestingDepth );
        outAst = inAst;
        goto finally;
    }

    switch ( inAst->kind )
    {
        case ZEND_AST_GLOBAL:
            visitAstGlobal( inAst );
            goto finally;

        case ZEND_AST_FUNC_DECL:
            outAst = transformAstFunction( inAst );
            goto finally;

        default:
            goto finally;
    }

    finally:
    ELASTIC_APM_LOG_TRACE( "%sExited - inAst: %s (line: %d), outAst: %s (line: %d)"
                           , streamIndent( nestingDepth, &txtOutStream )
                           , streamZendAstKind( inAst->kind, &txtOutStream ), (int)zend_ast_get_lineno( inAst )
                           , streamZendAstKind( outAst->kind, &txtOutStream ), (int)zend_ast_get_lineno( outAst ) );
    textOutputStreamRewind( &txtOutStream );
    return outAst;
}
#pragma clang diagnostic pop

void elasticApmProcessAst( zend_ast* ast )
{
    ELASTIC_APM_ASSERT( isOriginalZendAstProcessSet, "originalZendAstProcess: %p", originalZendAstProcess );

    zend_ast* outAst = ast;

    if ( ! isLoadingAgentPhpCode )
    {
        outAst = transformAst( outAst, /* nestingDepth: */ 0 );
    }

    if ( originalZendAstProcess != NULL )
    {
        originalZendAstProcess( outAst );
    }
}

void elasticApmAstInstrumentationOnModuleInit( const ConfigSnapshot* config )
{
    if ( config->astProcessEnabled )
    {
        originalZendAstProcess = zend_ast_process;
        isOriginalZendAstProcessSet = true;
        zend_ast_process = elasticApmProcessAst;
        ELASTIC_APM_LOG_DEBUG( "Changed zend_ast_process: from %p to elasticApmProcessAst (%p)", originalZendAstProcess, elasticApmProcessAst );
    } else {
        ELASTIC_APM_LOG_DEBUG( "AST processing will be DISABLED because configuration option %s (astProcessEnabled) is set to false", ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_ENABLED );
    }

    wordPressInstrumentationOnModuleInit();
}

void elasticApmAstInstrumentationOnModuleShutdown()
{
    wordPressInstrumentationOnModuleShutdown();

    if ( isOriginalZendAstProcessSet )
    {
        zend_ast_process_t zendAstProcessBeforeRestore = zend_ast_process;
        zend_ast_process = originalZendAstProcess;
        originalZendAstProcess = NULL;
        isOriginalZendAstProcessSet = false;
        ELASTIC_APM_LOG_DEBUG( "Restored zend_ast_process: from %p (%s elasticApmProcessAst: %p) -> %p"
                               , zendAstProcessBeforeRestore, zendAstProcessBeforeRestore == elasticApmProcessAst ? "==" : "!=", elasticApmProcessAst, originalZendAstProcess );
    }
}

void elasticApmAstInstrumentationOnRequestInit()
{
    wordPressInstrumentationOnRequestInit();
}

void elasticApmAstInstrumentationOnRequestShutdown()
{
    wordPressInstrumentationOnRequestShutdown();
}

