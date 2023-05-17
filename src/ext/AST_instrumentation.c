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
#include "ConfigSnapshot.h"
#include "ConfigManager.h"
#include "log.h"
#include "AST_debug.h"
#include <stdlib.h>
#include <php_version.h>
#include <zend_API.h>
#include <zend_types.h>
#include <zend_language_parser.h>
#include "WordPress_instrumentation.h"
#include "util.h"
#include "util_for_PHP.h"
#include "AST_util.h"
#include "elastic_apm_alloc.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_AUTO_INSTRUMENT

static bool g_isOriginalZendAstProcessSet = false;
static zend_ast_process_t g_originalZendAstProcess = NULL;

static bool g_isLoadingAgentPhpCode = false;

void elasticApmBeforeLoadingAgentPhpCode()
{
    g_isLoadingAgentPhpCode = true;
}

void elasticApmAfterLoadingAgentPhpCode()
{
    g_isLoadingAgentPhpCode = false;
}

bool getStringFromAstZVal( zend_ast* astZval, /* out */ StringView* pResult )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    if ( astZval == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astZval == NULL" );
        return false;
    }

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

bool getAstDeclName( zend_ast_decl* astDecl, /* out */ StringView* name )
{
    if ( astDecl->name == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - astAsDecl->name == NULL" );
        return false;
    }

    *name = zStringToStringView( astDecl->name );
    ELASTIC_APM_LOG_TRACE( "Returning true - name [length: %"PRIu64"]: %.*s", (UInt64)(name->length), (int)(name->length), name->begin );
    return true;
}

bool getAstFunctionParameters( zend_ast_decl* astDecl, /* out */ ZendAstPtrArrayView* paramsAsts )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( astDecl->kind == ZEND_AST_FUNC_DECL || astDecl->kind == ZEND_AST_METHOD, "astDecl->kind: %s", streamZendAstKind( astDecl->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_ASSERT_VALID_PTR( paramsAsts );

    // function list of parameters is always child[ 0 ] - see zend_compile_func_decl
    zend_ast* astFuncParams = astDecl->child[ 0 ];
    if ( ! ( ( astFuncParams->kind == ZEND_AST_PARAM_LIST ) && zend_ast_is_list( astFuncParams ) ) )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - zend_ast_is_list( astFuncParams ): %s, astFuncParams->kind: %s"
                               , boolToString( zend_ast_is_list( astFuncParams ) ), streamZendAstKind( astFuncParams->kind, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        return false;
    }
    zend_ast_list* astFuncParamsAsList = zend_ast_get_list( astFuncParams );

    paramsAsts->count = astFuncParamsAsList->children;
    paramsAsts->values = &( astFuncParamsAsList->child[ 0 ] );

    return true;
}

bool getAstFunctionParameterName( zend_ast_decl* astDecl, unsigned int parameterIndex, /* out */ StringView* name )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ZendAstPtrArrayView paramsAsts;

    if ( ! getAstFunctionParameters( astDecl, /* out */ &paramsAsts ) )
    {
        return false;
    }

    if ( parameterIndex >= paramsAsts.count )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - paramsAsts.count: %u, parameterIndex: %u", (unsigned)paramsAsts.count, parameterIndex );
        return false;
    }

    zend_ast* param = paramsAsts.values[ parameterIndex ];
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

    // parameter name is in child[ 1 ]
    return getStringFromAstZVal( param->child[ 1 ], /* out */ name );
}

zend_string* createZStringForAst( StringView inStr )
{
    return zend_string_init( inStr.begin, inStr.length, /* persistent: */ false );
}

bool isZendAstListKind( zend_ast_kind kind )
{
    return ((kind >> ZEND_AST_IS_LIST_SHIFT) & 1) != 0;
}

/**
 * zend_ast_create and zend_ast_create_ex allowed up to 4 child* parameters for version before PHP v8
 * and the limit was increased to 5 in PHP v8
 *
 * @see ZEND_AST_SPEC_CALL_EX
 */
static size_t g_maxCreateAstChildCount =
    #if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 0, 0 )
    4
    #else
    5
    #endif
;

zend_ast* createAstEx( zend_ast_kind kind, zend_ast_attr attr, ZendAstPtrArrayView children )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT_LE_UINT64( children.count, g_maxCreateAstChildCount );
    ELASTIC_APM_ASSERT( ! isZendAstListKind( kind ), "kind: %s", streamZendAstKind( kind, &txtOutStream ) );

    switch( children.count )
    {
        case 0:
            return zend_ast_create_ex( kind, attr );
        case 1:
            return zend_ast_create_ex( kind, attr, children.values[ 0 ] );
        case 2:
            return zend_ast_create_ex( kind, attr, children.values[ 0 ], children.values[ 1 ] );
        case 3:
            return zend_ast_create_ex( kind, attr, children.values[ 0 ], children.values[ 1 ], children.values[ 2 ] );
        case 4:
            return zend_ast_create_ex( kind, attr, children.values[ 0 ], children.values[ 1 ], children.values[ 2 ], children.values[ 3 ] );
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 0, 0 )
        case 5:
            return zend_ast_create_ex( kind, attr, children.values[ 0 ], children.values[ 1 ], children.values[ 2 ], children.values[ 3 ], children.values[ 4 ] );
        #endif
    }
}

ResultCode createAstExCheckChildrenCount( zend_ast_kind kind, zend_ast_attr attr, ZendAstPtrArrayView children, /* out */ zend_ast** pResult )
{
    ResultCode resultCode;

    if ( children.count > g_maxCreateAstChildCount )
    {
        ELASTIC_APM_LOG_ERROR( "Number of children is larger than max; children.count: %u, g_maxCreateAstChildCount: %u", (unsigned)children.count, (unsigned)g_maxCreateAstChildCount );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultFailure );
    }

    *pResult = createAstEx( kind, attr, children );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

zend_ast* createAstWithAttribute( zend_ast_kind kind, zend_ast_attr attr )
{
    return createAstEx( kind, attr, ELASTIC_APM_MAKE_EMPTY_ARRAY_VIEW( ZendAstPtrArrayView ) );
}

zend_ast* createAstWithAttributeAndOneChild( zend_ast_kind kind, zend_ast_attr attr, zend_ast* child )
{
    return createAstEx( kind, attr, ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, /* count */ 1, &child ) );
}

zend_ast* createAstWithAttributeAndTwoChildren( zend_ast_kind kind, zend_ast_attr attr, zend_ast* child0, zend_ast* child1 )
{
    zend_ast* children[] = { child0, child1 };
    return createAstEx( kind, attr, ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( ZendAstPtrArrayView, children ) );
}

zend_ast* createAstWithAttributeAndThreeChildren( zend_ast_kind kind, zend_ast_attr attr, zend_ast* child0, zend_ast* child1, zend_ast* child2 )
{
    zend_ast* children[] = { child0, child1, child2 };
    return createAstEx( kind, attr, ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( ZendAstPtrArrayView, children ) );
}

zend_ast* createAstWithOneChild( zend_ast_kind kind, zend_ast* child )
{
    return createAstWithAttributeAndOneChild( kind, /* attr */ 0, child );
}

zend_ast* createAstWithTwoChildren( zend_ast_kind kind, zend_ast* child0, zend_ast* child1 )
{
    return createAstWithAttributeAndTwoChildren( kind, /* attr */ 0, child0, child1 );
}

zend_ast* createAstWithThreeChildren( zend_ast_kind kind, zend_ast* child0, zend_ast* child1, zend_ast* child2 )
{
    return createAstWithAttributeAndThreeChildren( kind, /* attr */ 0, child0, child1, child2 );
}

zend_ast* createAstMagicConst( zend_ast_attr attr, uint32_t lineNumber )
{
    zend_ast* result = createAstWithAttribute( ZEND_AST_MAGIC_CONST, attr );
    result->lineno = lineNumber;
    return result;
}

zend_ast* createAstMagicConst__FUNCTION__( uint32_t lineNumber )
{
    return createAstMagicConst( T_FUNC_C, lineNumber );
}

zend_ast* createAstMagicConst__CLASS__( uint32_t lineNumber )
{
    return createAstMagicConst( T_CLASS_C, lineNumber );
}

zend_ast* createAstZValWithAttribute( zval* zv, zend_ast_attr attr, uint32_t lineNumber )
{
    zend_ast* result = zend_ast_create_zval_with_lineno(
        zv,
        #if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version before 7.3.0 */
        attr,
        #endif
        lineNumber
    );

    #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version from 7.3.0 */
    result->attr = attr;
    #endif

    return result;
}

zend_ast* createAstZValStringWithAttribute( StringView inStr, zend_ast_attr attr, uint32_t lineNumber )
{
    zend_string* asZString = createZStringForAst( inStr );
    zval stringAsZVal;
    ZVAL_NEW_STR( &stringAsZVal, asZString );
    return createAstZValWithAttribute( &stringAsZVal, attr, lineNumber );
}

zend_ast* createAstZValString( StringView inStr, uint32_t lineNumber )
{
    return createAstZValStringWithAttribute( inStr, /* attr */ 0, lineNumber );
}

zend_ast* createAstVar( StringView name, uint32_t lineNumber )
{
    //    ZEND_AST_VAR (256) (line: 121)
    //        ZEND_AST_ZVAL (64) (line: 483) [type: string, value: hook_name]

    return createAstWithOneChild( /* kind */ ZEND_AST_VAR, createAstZValString( name, lineNumber ) );
}

zend_ast* createAstConst( StringView name, zend_ast_attr nameAstAttr, uint32_t lineNumber )
{
    //    ZEND_AST_CONST (line: 20, attr: 0, childCount: 1)
    //        ZEND_AST_ZVAL (line: 20, attr: 1) [type: string, value: null]

    zend_ast* nameAst = createAstZValString( name, lineNumber );
    nameAst->attr = nameAstAttr;
    return createAstWithOneChild( /* kind */ ZEND_AST_CONST, /* child0 */ nameAst );
}

zend_ast* createAstConstNull( uint32_t lineNumber )
{
    return createAstConst( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "null" ), ZEND_NAME_NOT_FQ, lineNumber );
}

zend_ast* createAstGlobalConst( StringView name, uint32_t lineNumber )
{
    return createAstConst( name, /* nameAstAttr */ 0, lineNumber );
}

/**
 * @see zend_ast_create_list_* in zend_ast.h
 */
static size_t g_elasticApmCreateAstListExChildrenCount = 2;

zend_ast* createAstListEx( zend_ast_kind kind, zend_ast_attr attr, ZendAstPtrArrayView children )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT_LE_UINT64( children.count, g_elasticApmCreateAstListExChildrenCount );
    ELASTIC_APM_ASSERT( isZendAstListKind( kind ), "kind: %s", streamZendAstKind( kind, &txtOutStream ) );

    zend_ast* result = NULL;

    switch( children.count )
    {
        case 0:
            result = zend_ast_create_list( children.count, kind );
            break;
        case 1:
            result = zend_ast_create_list( children.count, kind, children.values[ 0 ] );
            break;
        case 2:
            result = zend_ast_create_list( children.count, kind, children.values[ 0 ], children.values[ 1 ] );
            break;
    }

    zend_ast_list* resultAsList = (zend_ast_list*)result;
    resultAsList->attr = attr;
    return result;
}

zend_ast* createAstListWithAttribute( zend_ast_kind kind, zend_ast_attr attr, uint32_t lineNumber )
{
    zend_ast* result = createAstListEx( kind, attr, ELASTIC_APM_MAKE_EMPTY_ARRAY_VIEW( ZendAstPtrArrayView ) );
    ((zend_ast_list*)result)->lineno = lineNumber;
    return result;
}

zend_ast* createAstList( zend_ast_kind kind, uint32_t lineNumber )
{
    return createAstListWithAttribute( kind, /* attr */ 0, lineNumber );
}

void addChildToAstList( zend_ast* child, /* in,out */ zend_ast** pInSrcListOutNewList )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( pInSrcListOutNewList );

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_ASSERT( zend_ast_is_list( *pInSrcListOutNewList ), "kind: %s", streamZendAstKind( (*pInSrcListOutNewList)->kind, &txtOutStream ) );

    zend_ast* newList = zend_ast_list_add( /* in */ *pInSrcListOutNewList, child );
    *pInSrcListOutNewList = newList;
}

zend_ast* createAstListWithOneChild( zend_ast_kind kind, zend_ast* child )
{
    return createAstListEx( kind, /* attr */ 0, ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, /* count */ 1, &child ) );
}

zend_ast* createAstListWithTwoChildren( zend_ast_kind kind, zend_ast* child0, zend_ast* child1 )
{
    zend_ast* children[] = { child0, child1 };
    return createAstListEx( kind, /* attr */ 0, ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( ZendAstPtrArrayView, children ) );
}

zend_ast* createAstListWithThreeChildren( zend_ast_kind kind, zend_ast* child0, zend_ast* child1, zend_ast* child2 )
{
    zend_ast* result = createAstListWithTwoChildren( kind, child0, child1 );
    addChildToAstList( child2, /* in,out */ &result );
    return result;
}

ResultCode createCapturedArgsAstArray( zend_ast_decl* astDecl, ArgCaptureSpecArrayView argCaptureSpecs, uint32_t lineNumber, /* out */ zend_ast** pResult )
{
    // AST for PHP code:
    //
    //    <pre-hook event handler>(..., [$hook_name, &$callback])
    //                                  ^^^^^^^^^^^^^^^^^^^^^^^^ - captured args AST array
    //
    //    ZEND_AST_ARRAY (129) (line: 121, attr: 3) <- [$hook_name, /* ref */ &$callback] and 3 == ZEND_ARRAY_SYNTAX_SHORT
    //        ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 0)
    //            ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value: hook_name]
    //            NULL
    //        ZEND_AST_ARRAY_ELEM (526) (line: 121, attr: 1) <- attr == 1 because callback variable is taken by reference
    //            ZEND_AST_VAR (256) (line: 121, attr: 0)
    //                ZEND_AST_ZVAL (64) (line: 121, attr: 0) [type: string, value: callback]
    //            NULL

    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pResult );

    ResultCode resultCode;
    zend_ast* result = createAstListWithAttribute( ZEND_AST_ARRAY, /* attr */ ZEND_ARRAY_SYNTAX_SHORT, lineNumber );

    ELASTIC_APM_FOR_EACH_INDEX( i, argCaptureSpecs.count )
    {
        StringView parameterName;
        ArgCaptureSpec argCaptureSpec = argCaptureSpecs.values[ i ];
        if ( argCaptureSpec == dontCaptureArg )
        {
            continue;
        }
        ELASTIC_APM_ASSERT( argCaptureSpec == captureArgByRef || argCaptureSpec == captureArgByValue, "argCaptureSpec: %d, i: %d", argCaptureSpec, (int)i );
        // ZEND_AST_ARRAY_ELEM attribute should be 1 when passed by reference and 0 when passed by value
        zend_ast_attr arrayElemAttr = argCaptureSpec == captureArgByRef ? 1 : 0;
        if ( ! getAstFunctionParameterName( astDecl, /* parameterIndex */ i, /* out */ &( parameterName ) ) )
        {
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
        zend_ast* varAst = createAstVar( parameterName, lineNumber );
        // Array element value is the first child (i.e., index 0) and array element key is the second child (i.e., index 1)
        zend_ast* arrayElement = createAstWithAttributeAndTwoChildren( ZEND_AST_ARRAY_ELEM, arrayElemAttr, /* array element value */ varAst, /* array element key */ NULL );
        addChildToAstList( arrayElement, /* in,out */ &result );
    }

    *pResult = result;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

zend_ast* createAstStandaloneFunctionCall( StringView funcName, bool isFullyQualified, zend_ast* astArgList )
{
    // AST for PHP code:
    //
    //    \elastic_apm_ast_instrumentation_pre_hook(__CLASS__, __FUNCTION__, [$hook_name, &$callback])
    //
    //    ZEND_AST_CALL (line: 15, attr: 0, childCount: 2)
    //        ZEND_AST_ZVAL (line: 15, attr: 0) [type: string, value: elastic_apm_ast_instrumentation_pre_hook]
    //        ZEND_AST_ARG_LIST (line: 15, attr: 0, childCount: 3)

    uint32_t lineNumber = zend_ast_get_lineno( astArgList );
    zend_ast_attr zValAttr = isFullyQualified ? ZEND_NAME_FQ : ZEND_NAME_NOT_FQ;
    return createAstWithTwoChildren( ZEND_AST_CALL, createAstZValStringWithAttribute( funcName, zValAttr, lineNumber ), astArgList );
}

zend_ast* createAstStandaloneFqFunctionCall( StringView funcName, zend_ast* astArgList )
{
    return createAstStandaloneFunctionCall( funcName, /* isFullyQualified */ true, astArgList );
}

zend_ast* createAstStandaloneNotFqFunctionCall( StringView funcName, zend_ast* astArgList )
{
    return createAstStandaloneFunctionCall( funcName, /* isFullyQualified */ false, astArgList );
}

ResultCode createPreHookAstArgListByCaptureSpec( zend_ast_decl* astDecl, ArgCaptureSpecArrayView argCaptureSpecs, /* out */ zend_ast** pResult )
{
    // AST for PHP code:
    //
    //    \elastic_apm_ast_instrumentation_pre_hook(<instrumented class full name>, __FUNCTION__, [$hook_name, &$callback])
    //                                               ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    //    <instrumented class full name> is __CLASS__ for methods and null for standalone functions
    //
    //    ZEND_AST_ARG_LIST (line: 15, attr: 0, childCount: 3)
    //        ZEND_AST_MAGIC_CONST (line: 15, attr: __CLASS__)
    //        ZEND_AST_MAGIC_CONST (line: 15, attr: __FUNCTION__)
    //        ZEND_AST_ARRAY (line: 15, attr: 3, childCount: 2)

    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pResult );

    ResultCode resultCode;
    uint32_t lineNumber = astDecl->start_lineno;
    zend_ast* capturedArgsAstArray = NULL;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createCapturedArgsAstArray( astDecl, argCaptureSpecs, lineNumber, /* out */ &capturedArgsAstArray ) );

    *pResult = createAstListWithThreeChildren(
            ZEND_AST_ARG_LIST
            , astDecl->kind == ZEND_AST_METHOD ? createAstMagicConst__CLASS__( lineNumber ) : createAstConstNull( lineNumber )
            , createAstMagicConst__FUNCTION__( lineNumber )
            , capturedArgsAstArray
    );
    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG();
    return resultCode;

    failure:
    goto finally;
}

static StringView g_elastic_apm_ast_instrumentation_pre_hook_funcName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "elastic_apm_ast_instrumentation_pre_hook" );

/**
 * function body is always child[ 2 ]
 *
 * @see zend_compile_func_decl
 */
static const size_t g_funcDeclBodyChildIndex = 2;

ResultCode insertAstForFunctionPreHook( zend_ast_decl* funcAstDecl, ArgCaptureSpecArrayView argCaptureSpecs )
{
    // Before:
    //
    //    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
    //        ////////////////////////////
    //        // original function body //
    //        ////////////////////////////
    //    }
    //
    //    ZEND_AST_FUNC_DECL (name: add_filter, line: 7, flags: 0, attr: 0, childCount: 4)
    //        ZEND_AST_PARAM_LIST (line: 7, attr: 0, childCount: 4)
    //        NULL
    //        ZEND_AST_STMT_LIST (line: 7, attr: 0, childCount: 4)                                                                                                             <- original function body
    //        NULL
    //
    // After:
    //
    //    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) { /* fold-into-one-line-begin */
    //        \elastic_apm_ast_instrumentation_pre_hook( /* pre-hook args */ );
    //        { /* fold-into-one-line-end */
    //        ////////////////////////////
    //        // original function body //
    //        ////////////////////////////
    //    } }
    //
    //    ZEND_AST_FUNC_DECL (name: add_filter, line: 24, flags: 0, attr: 0, childCount: 4)
    //        ZEND_AST_PARAM_LIST (line: 24, attr: 0, childCount: 4)
    //        NULL
    //        ZEND_AST_STMT_LIST (line: 24, attr: 0, childCount: 2)                                                                                                                 <- new function body
    //            ZEND_AST_CALL (line: 24, attr: 0, childCount: 2)
    //                ZEND_AST_ZVAL (line: 24, attr: 0) [type: string, value: elastic_apm_ast_instrumentation_pre_hook]
    //                ZEND_AST_ARG_LIST (line: 24, attr: 0, childCount: 3)                                                                                                              <- pre-hook args
    //            ZEND_AST_STMT_LIST (line: 24, attr: 0, childCount: 4)                                                                                                        <- original function body
    //        NULL

    ELASTIC_APM_ASSERT_VALID_PTR( funcAstDecl );

    ResultCode resultCode;
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String dbgCompiledFileName = stringIfNotNullElse( nullableZStringToStringView( CG(compiled_filename) ).begin, "<N/A>" );

    ELASTIC_APM_ASSERT( funcAstDecl->kind == ZEND_AST_FUNC_DECL || funcAstDecl->kind == ZEND_AST_METHOD, "funcAstDecl->kind: %s", streamZendAstKind( funcAstDecl->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    StringView dbgFuncName;
    if ( ! getAstDeclName( funcAstDecl, /* out */ &dbgFuncName ) )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to get function name - returning failure" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "dbgFuncName: %s, compiled_filename: %s", dbgFuncName.begin, dbgCompiledFileName );
    debugDumpAstTreeToLog( (zend_ast*) funcAstDecl, logLevel_debug );

    zend_ast* originalFuncBodyAst = funcAstDecl->child[ g_funcDeclBodyChildIndex ];
    if ( originalFuncBodyAst == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "originalFuncBodyAst == NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    if ( originalFuncBodyAst->kind != ZEND_AST_STMT_LIST )
    {
        ELASTIC_APM_LOG_TRACE( "Expected originalFuncBodyAst->kind to be ZEND_AST_STMT_LIST but it is %s", streamZendAstKind( originalFuncBodyAst->kind, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    zend_ast* preHookCallAstArgList = NULL;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createPreHookAstArgListByCaptureSpec( funcAstDecl, argCaptureSpecs, /* out */ &preHookCallAstArgList ) );

    funcAstDecl->child[ g_funcDeclBodyChildIndex ] = createAstListWithTwoChildren(
            ZEND_AST_STMT_LIST
            , createAstStandaloneFqFunctionCall( g_elastic_apm_ast_instrumentation_pre_hook_funcName, preHookCallAstArgList )
            , originalFuncBodyAst
    );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG();
    debugDumpAstTreeToLog( (zend_ast*) funcAstDecl, logLevel_debug );
    return resultCode;

    failure:
    goto finally;
}

zend_ast* createDirectCallAstArgList( uint32_t lineNumber, StringView constNameForMethodName )
{
    // PHP code:
    //
    //    \elastic_apm_ast_instrumentation_direct_call(\ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS);
    //                                                  ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    //
    // AST:
    //
    //    ZEND_AST_ARG_LIST (line: 63, attr: 0, childCount: 1)
    //        ZEND_AST_CONST (line: 63, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 63, attr: 0) [type: string, value: ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS]

    return createAstListWithOneChild( ZEND_AST_ARG_LIST, createAstGlobalConst( constNameForMethodName, lineNumber ) );
}

static StringView g_elastic_apm_ast_instrumentation_direct_call_funcName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "elastic_apm_ast_instrumentation_direct_call" );

ResultCode appendDirectCallToInstrumentation( zend_ast_decl** pAstChildSlot, StringView constNameForMethodName )
{
    // Before:
    //
    //    function _wp_filter_build_unique_id( $hook_name, $callback, $priority ) {
    //        // ...
    //    }
    //
    //    ZEND_AST_FUNC_DECL (name: _wp_filter_build_unique_id, line: 44, flags: 0, attr: 0, childCount: 4)                                                             <- original function declaration
    //
    // After:
    //
    //    { function _wp_filter_build_unique_id($hook_name, $callback, $priority ) { markerForElasticApmTestsFoldAstIntoOneLineBegin();
    //        // ...
    //        } } /* fold-into-one-line-begin */
    //    \elastic_apm_ast_instrumentation_direct_call(/* direct call args */);
    //    /* fold-into-one-line-end */ }
    //
    //    ZEND_AST_STMT_LIST (line: 44, attr: 0, childCount: 2)
    //        ZEND_AST_FUNC_DECL (name: _wp_filter_build_unique_id, line: 44, flags: 0, attr: 0, childCount: 4)                                                         <- original function declaration
    //        ZEND_AST_CALL (line: 63, attr: 0, childCount: 2)
    //            ZEND_AST_ZVAL (line: 63, attr: 0) [type: string, value: elastic_apm_ast_instrumentation_direct_call]

    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( pAstChildSlot );

    ResultCode resultCode;
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String dbgCompiledFileName = stringIfNotNullElse( nullableZStringToStringView( CG(compiled_filename) ).begin, "<N/A>" );
    zend_ast_decl* appendToAstDecl = *pAstChildSlot;
    uint32_t lineNumber = appendToAstDecl->end_lineno;

    ELASTIC_APM_ASSERT( appendToAstDecl->kind == ZEND_AST_FUNC_DECL, "appendToAst->kind: %s", streamZendAstKind( appendToAstDecl->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    StringView dbgFuncName;
    if ( ! getAstDeclName( appendToAstDecl, /* out */ &dbgFuncName ) )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to get function name - returning failure" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "dbgFuncName: %s, compiled_filename: %s", dbgFuncName.begin, dbgCompiledFileName );
    debugDumpAstTreeToLog( (zend_ast*) ( *pAstChildSlot ), logLevel_debug );

    zend_ast* appendedCallAstArgList = createDirectCallAstArgList( lineNumber, constNameForMethodName );
    zend_ast* appendedCallAst = createAstStandaloneFqFunctionCall( g_elastic_apm_ast_instrumentation_direct_call_funcName, appendedCallAstArgList );

    *((zend_ast**)pAstChildSlot) = createAstListWithTwoChildren( ZEND_AST_STMT_LIST, (zend_ast*) appendToAstDecl, appendedCallAst );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG( "dbgFuncName: %s, compiled_filename: %s", dbgFuncName.begin, dbgCompiledFileName );
    debugDumpAstTreeToLog( (zend_ast*) ( *pAstChildSlot ), logLevel_debug );
    return resultCode;

    failure:
    goto finally;
}

static StringView g_wrappedFunctionNewNameSuffix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "ElasticApmWrapped" );

ResultCode createWrappedFunctionNewName( StringView originalName, /* out */ StringBuffer* pResult )
{
    ResultCode resultCode;
    StringBuffer result = ELASTIC_APM_EMPTY_STRING_BUFFER;
    size_t newNameLength = originalName.length + g_wrappedFunctionNewNameSuffix.length;
    size_t contentLength = 0;

    ELASTIC_APM_MALLOC_STRING_BUFFER_IF_FAILED_GOTO( /* maxLength */ newNameLength, /* out */ result );
    result.begin[ 0 ] = '\0';

    ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( originalName, result, /* in,out */ &contentLength ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( g_wrappedFunctionNewNameSuffix, result, /* in,out */ &contentLength ) );
    ELASTIC_APM_ASSERT_EQ_UINT64( contentLength, newNameLength );

    *pResult = result;
    result = ELASTIC_APM_EMPTY_STRING_BUFFER;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ result );
    goto finally;
}

zend_string* cloneZStringForAst( zend_string* src )
{
    if ( src == NULL )
    {
        return NULL;
    }

    return createZStringForAst( zStringToStringView( src ) );
}

zend_ast* cloneAstZVal( zend_ast* ast, uint32_t lineNumber )
{
    zval clonedZVal;
    ZVAL_COPY( /* out */ &clonedZVal, zend_ast_get_zval( ast ) );
    return createAstZValWithAttribute( &clonedZVal, ast->attr, lineNumber );
}

// ZEND_AST_CONSTANT was added in PHP 7.3
#if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version from 7.3.0 */
zend_ast* cloneAstConstant( zend_ast* ast, uint32_t lineNumber )
{
    zend_ast* result = zend_ast_create_constant( zend_ast_get_constant_name( ast ), ast->attr );
    zval* pResultZVal = zend_ast_get_zval( result );
    Z_LINENO_P( pResultZVal ) = lineNumber;
    return result;
}
#endif

ResultCode cloneAstTree( zend_ast* ast, uint32_t lineNumber, /* out */ zend_ast** pResult );

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
ResultCode cloneAstDecl( zend_ast* ast, uint32_t lineNumber, /* out */ zend_ast** pResult )
{
    ResultCode resultCode;
    ZendAstPtrArrayView children = getAstChildren( ast );
    zend_ast_decl* astDecl = (zend_ast_decl*)ast;
    zend_ast** clonedChildren = NULL;

    if ( children.count != elasticApmZendAstDeclChildrenCount )
    {
        ELASTIC_APM_LOG_ERROR( "Number of children is not as expected; children.count: %u, elasticApmZendAstDeclChildrenCount: %u"
                               , (unsigned)children.count, (unsigned)elasticApmZendAstDeclChildrenCount );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultFailure );
    }

    clonedChildren = emalloc( sizeof( zend_ast* ) * children.count );
    ELASTIC_APM_FOR_EACH_INDEX( i, children.count )
    {
        clonedChildren[ i ] = NULL;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneAstTree( children.values[ i ], lineNumber, /* out */ &( clonedChildren[ i ] ) ) );
    }

    *pResult = zend_ast_create_decl(
        astDecl->kind
        , astDecl->flags
        , lineNumber /* <- start_lineno */
        , cloneZStringForAst( astDecl->doc_comment )
        , cloneZStringForAst( astDecl->name )
        , clonedChildren[ 0 ]
        , clonedChildren[ 1 ]
        , clonedChildren[ 2 ]
        , clonedChildren[ 3 ]
            /**
             * number of child* parameters accepted by zend_ast_create_decl
             *      4 before PHP v8.0.0
             *      5 from PHP v8.0.0
             */
            #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 0, 0 )
        , clonedChildren[ 4 ]
            #endif
    );

    resultCode = resultSuccess;
    finally:
    if ( clonedChildren != NULL )
    {
        efree( clonedChildren );
        clonedChildren = NULL;
    }
    return resultCode;

    failure:
    goto finally;
}

ResultCode cloneAstList( zend_ast* ast, uint32_t lineNumber, /* out */ zend_ast** pResult )
{
    ResultCode resultCode;
    zend_ast_list* astList = zend_ast_get_list( ast );
    zend_ast* result = createAstListWithAttribute( astList->kind, astList->attr, lineNumber );
    ELASTIC_APM_FOR_EACH_INDEX( i, astList->children )
    {
        zend_ast* clonedChildAst = NULL;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneAstTree( astList->child[ i ], lineNumber, /* out */ &clonedChildAst ) );
        addChildToAstList( clonedChildAst, /* in,out */ &result );
    }

    *pResult = result;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode cloneFallbackAst( zend_ast* ast, uint32_t lineNumber, /* out */ zend_ast** pResult )
{
    ResultCode resultCode;
    ZendAstPtrArrayView children = getAstChildren( ast );
    zend_ast** clonedChildren = NULL;

    if ( children.count != 0 )
    {
        clonedChildren = emalloc( sizeof( zend_ast* ) * children.count );
    }
    ELASTIC_APM_FOR_EACH_INDEX( i, children.count )
    {
        clonedChildren[ i ] = NULL;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneAstTree( children.values[ i ], lineNumber, /* out */ &( clonedChildren[ i ] ) ) );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createAstExCheckChildrenCount( ast->kind, ast->attr,  ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, children.count, clonedChildren ), /* out */ pResult ) );
    (*pResult)->lineno = lineNumber;

    resultCode = resultSuccess;
    finally:
    if ( clonedChildren != NULL )
    {
        efree( clonedChildren );
        clonedChildren = NULL;
    }
    return resultCode;

    failure:
    goto finally;
}

ResultCode cloneAstTree( zend_ast* ast, uint32_t lineNumber, /* out */ zend_ast** pResult )
{
    /**
     * @see zend_ast_copy
     */

    ResultCode resultCode;

    if ( ast == NULL )
    {
        *pResult = NULL;
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    if ( ast->kind == ZEND_AST_ZVAL )
    {
        *pResult = cloneAstZVal( ast, lineNumber );
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    // ZEND_AST_CONSTANT was added in PHP 7.3
    #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version from 7.3.0 */
    if ( ast->kind == ZEND_AST_CONSTANT )
    {
        *pResult = cloneAstConstant( ast, lineNumber );
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }
    #endif

    if ( isAstDecl( ast->kind ) )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneAstDecl( ast, lineNumber, /* out */ pResult ) );
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    if ( zend_ast_is_list( ast ) )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneAstList( ast, lineNumber, /* out */ pResult ) );
        ELASTIC_APM_SET_RESULT_CODE_TO_SUCCESS_AND_GOTO_FINALLY();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( cloneFallbackAst( ast, lineNumber, /* out */ pResult ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}
#pragma clang diagnostic pop

zend_ast* createAstAssign( StringView varName, zend_ast* rhsAst )
{
    // PHP code:
    //
    //    $args = func_get_args();
    //    $postHook = \elastic_apm_ast_instrumentation_pre_hook(/* instrumentedClassFullName */ null, __FUNCTION__, $args);
    //
    // AST:
    //
    //    ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2)
    //        ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: args)
    //        ZEND_AST_CALL (line: 48, attr: 0, childCount: 2)                                                  <- rhsAst

    return createAstWithTwoChildren( ZEND_AST_ASSIGN, createAstVar( varName, zend_ast_get_lineno( rhsAst ) ), rhsAst );
}

static StringView g_argsVarName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "args" );
static StringView g_postHookVarName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "postHook" );

zend_ast* createPreHookAstArgList( bool isMethod, uint32_t lineNumber )
{
    // PHP code:
    //
    //    \elastic_apm_ast_instrumentation_pre_hook(<instrumented class full name>, __FUNCTION__, $args);
    //                                               ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    //    <instrumented class full name> is __CLASS__ for methods and null for standalone functions
    //
    // AST:
    //
    //    ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 3)
    //        ZEND_AST_CONST (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: null)
    //        ZEND_AST_MAGIC_CONST (line: 48, attr: __FUNCTION__, childCount: 0)
    //        ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: args)

    return createAstListWithThreeChildren(
        ZEND_AST_ARG_LIST
        , isMethod ? createAstMagicConst__CLASS__( lineNumber ) : createAstConstNull( lineNumber )
        , createAstMagicConst__FUNCTION__( lineNumber )
        , createAstVar( g_argsVarName, lineNumber )
    );
}

void createWrapperFunctionBodyPrologAst( /* in,out */ zend_ast** appendToAstStmtList )
{
    // PHP code:
    //
    //    $args = func_get_args();
    //    $postHook = \elastic_apm_ast_instrumentation_pre_hook(/* instrumentedClassFullName */ null, __FUNCTION__, $args);
    //
    // AST:
    //
    //    ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2)
    //        ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: args)
    //        ZEND_AST_CALL (line: 48, attr: 0, childCount: 2)
    //            ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: func_get_args)
    //            ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 0)
    //    ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2)
    //        ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: postHook)
    //        ZEND_AST_CALL (line: 48, attr: 0, childCount: 2)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: elastic_apm_ast_instrumentation_pre_hook)
    //            ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 3)

    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( appendToAstStmtList );

    uint32_t lineNumber = zend_ast_get_lineno( *appendToAstStmtList );
    zend_ast* func_get_args_astCall = createAstStandaloneFqFunctionCall( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "func_get_args" ), createAstList( ZEND_AST_ARG_LIST, lineNumber ) );
    addChildToAstList( createAstAssign( g_argsVarName, func_get_args_astCall ), /* in,out */ appendToAstStmtList );
    zend_ast* preHookAstCall = createAstStandaloneFqFunctionCall( g_elastic_apm_ast_instrumentation_pre_hook_funcName, createPreHookAstArgList( /* isMethod */ false, lineNumber ) );
    addChildToAstList( createAstAssign( g_postHookVarName, preHookAstCall ), /* in,out */ appendToAstStmtList );
}

zend_ast* createCallPostHookIfNotNullAst( zend_ast* thrownAst, zend_ast* retValAst )
{
    // PHP code:
    //
    //    if ($postHook !== null) $postHook(/* thrown */ null, $retVal);
    //
    //      or
    //
    //    if ($postHook !== null) $postHook($thrown, /* retVal */ null);
    //
    // AST:
    //
    //    ZEND_AST_IF (line: 48, attr: 0, childCount: 1)
    //        ZEND_AST_IF_ELEM (line: 48, attr: 0, childCount: 2)
    //            ZEND_AST_BINARY_OP (line: 48, attr: 17, childCount: 2)
    //                ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                    ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: postHook)
    //                ZEND_AST_CONST (line: 48, attr: 0, childCount: 1)
    //                    ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: null)
    //            ZEND_AST_CALL (line: 48, attr: 0, childCount: 2)
    //                ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                    ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: postHook)
    //                ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 2)
    //                    ZEND_AST_CONST (line: 48, attr: 0, childCount: 1)
    //                        ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: null)
    //                    ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                        ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: retVal)
    //
    //  or
    //
    //                ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 2)
    //                    ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                        ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: thrown)
    //                    ZEND_AST_CONST (line: 48, attr: 0, childCount: 1)
    //                        ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: null)

    ELASTIC_APM_ASSERT_VALID_PTR( thrownAst );
    ELASTIC_APM_ASSERT_VALID_PTR( retValAst );

    uint32_t lineNumber = zend_ast_get_lineno( thrownAst );

    return createAstListWithOneChild(
        ZEND_AST_IF
        , createAstWithTwoChildren(
            ZEND_AST_IF_ELEM
            , zend_ast_create_binary_op(
                ZEND_IS_NOT_IDENTICAL
                , createAstVar( g_postHookVarName, lineNumber )
                , createAstConstNull( lineNumber )
            )
            , createAstWithTwoChildren(
                ZEND_AST_CALL
                , createAstVar( g_postHookVarName, lineNumber )
                , createAstListWithTwoChildren( ZEND_AST_ARG_LIST, thrownAst, retValAst )
            )
        )
    );
}

zend_ast* createWrappedFunctionCallAstArgList( uint32_t lineNumber )
{
    // PHP code:
    //
    //    get_templateElasticApmWrapped(...$args);
    //                                  ^^^^^^^^
    //
    // AST:
    //
    //    ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 1)
    //        ZEND_AST_UNPACK (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: args)

    return createAstListWithOneChild(
        ZEND_AST_ARG_LIST
        , createAstWithOneChild(
            ZEND_AST_UNPACK
            , createAstVar( g_argsVarName, lineNumber )
        )
    );
}

static StringView g_retValVarName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "retVal" );

zend_ast* createWrapperFunctionBodyTryBlockAst( StringView wrappedFunctionNewName, uint32_t lineNumber )
{
    // PHP code:
    //
    //    $retVal = get_templateElasticApmWrapped(...$args);
    //    if ($postHook !== null) $postHook(/* thrown */ null, $retVal);
    //    return $retVal;
    //
    // AST:
    //
    //    ZEND_AST_STMT_LIST (line: 48, attr: 0, childCount: 3)
    //        ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2)
    //            ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: retVal)
    //            ZEND_AST_CALL (line: 48, attr: 0, childCount: 2)
    //                ZEND_AST_ZVAL (line: 48, attr: 1, childCount: 0, type: string, value: get_templateElasticApmWrapped)
    //                ZEND_AST_ARG_LIST (line: 48, attr: 0, childCount: 1)
    //        ZEND_AST_IF (line: 48, attr: 0, childCount: 1)                                                                        <- if ($postHook !== null) $postHook(/* thrown */ null, $retVal);
    //        ZEND_AST_RETURN (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: retVal)

//    return createAstList( ZEND_AST_STMT_LIST, lineNumber );

    return createAstListWithThreeChildren(
        ZEND_AST_STMT_LIST
        , createAstAssign( g_retValVarName, createAstStandaloneNotFqFunctionCall( wrappedFunctionNewName, createWrappedFunctionCallAstArgList( lineNumber ) ) )
        , createCallPostHookIfNotNullAst( /* thrownAst */ createAstConstNull( lineNumber ), createAstVar( g_retValVarName, lineNumber ) )
        , createAstWithOneChild( ZEND_AST_RETURN, createAstVar( g_retValVarName, lineNumber ) )
    );
}

static StringView g_thrownVarName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "thrown" );

zend_ast* createWrapperFunctionBodyCatchPartAst( uint32_t lineNumber )
{
    // PHP code:
    //
    //    } catch (\Throwable $thrown) {
    //        if ($postHook !== null) $postHook($thrown, /* retVal */ null);
    //        throw $thrown;
    //    }
    //
    // AST:
    //
    //    ZEND_AST_CATCH_LIST (line: 48, attr: 0, childCount: 1)
    //        ZEND_AST_CATCH (line: 48, attr: 0, childCount: 3)
    //            ZEND_AST_NAME_LIST (line: 48, attr: 0, childCount: 1)
    //                ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: Throwable)
    //            ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: thrown)
    //            ZEND_AST_STMT_LIST (line: 48, attr: 0, childCount: 2)
    //                ZEND_AST_IF (line: 48, attr: 0, childCount: 1)                                                                <- if ($postHook !== null) $postHook($thrown, /* retVal */ null);
    //                ZEND_AST_THROW (line: 48, attr: 0, childCount: 1)
    //                    ZEND_AST_VAR (line: 48, attr: 0, childCount: 1)
    //                        ZEND_AST_ZVAL (line: 48, attr: 0, childCount: 0, type: string, value: thrown)

    /**
     * @see zend_compile_try
     */

    return createAstListWithOneChild(
        ZEND_AST_CATCH_LIST
        , createAstWithThreeChildren(
            ZEND_AST_CATCH
            /* ZEND_AST_CATCH child[ 0 ] - class name(s) */
            , createAstListWithOneChild( ZEND_AST_NAME_LIST, createAstZValString( ELASTIC_APM_STRING_LITERAL_TO_VIEW( "Throwable" ), lineNumber ) )
            /* ZEND_AST_CATCH child[ 1 ] - var name */
            , createAstZValString( g_thrownVarName, lineNumber )
            /* ZEND_AST_CATCH child[ 2 ] - block */
            , createAstListWithTwoChildren(
                ZEND_AST_STMT_LIST
                , createCallPostHookIfNotNullAst( createAstVar( g_thrownVarName, lineNumber ), /* retValAst */ createAstConstNull( lineNumber ) )
                , createAstWithOneChild( ZEND_AST_THROW, createAstVar( g_thrownVarName, lineNumber ) )
            )
        )
    );
}

void createWrapperFunctionBodyTryCatchAst( StringView wrappedFunctionNewName, /* in,out */ zend_ast** appendToAstStmtList )
{
    // PHP code:
    //
    //    try {
    //          // ...
    //    } catch ( ... ) {
    //          // ...
    //    }
    //
    // AST:
    //
    //    ZEND_AST_TRY (line: 48, attr: 0, childCount: 3)
    //        ZEND_AST_STMT_LIST (line: 48, attr: 0, childCount: 3)         <- try block
    //        ZEND_AST_CATCH_LIST (line: 48, attr: 0, childCount: 1)
    //            ZEND_AST_CATCH (line: 48, attr: 0, childCount: 3)         <- catch block
    //        NULL                                                          <- no finally block

    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( appendToAstStmtList );

    uint32_t lineNumber = zend_ast_get_lineno( *appendToAstStmtList );

    /**
     * @see zend_compile_try
     */
    zend_ast* astTryCatch = createAstWithThreeChildren(
        ZEND_AST_TRY
        , createWrapperFunctionBodyTryBlockAst( wrappedFunctionNewName, lineNumber )
        , createWrapperFunctionBodyCatchPartAst( lineNumber )
        , NULL /* <- no finally block */
    );

    addChildToAstList( astTryCatch, /* in,out */ appendToAstStmtList );
}

zend_ast* createWrapperFunctionBodyAst( StringView wrappedFunctionNewName, uint32_t lineNumber )
{
    // PHP code:
    //
    //    // prolog
    //    try {
    //          // ...
    //    } catch ( ... ) {
    //          // ...
    //    }
    //
    // AST:
    //
    //    ZEND_AST_STMT_LIST (line: 48, attr: 0, childCount: 3)
    //        ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2) <- // part of prolog
    //        ZEND_AST_ASSIGN (line: 48, attr: 0, childCount: 2) <- // part of prolog
    //        ZEND_AST_TRY (line: 48, attr: 0, childCount: 3)

    zend_ast* funcBodyAstStmtList = createAstList( ZEND_AST_STMT_LIST, lineNumber );

    createWrapperFunctionBodyPrologAst( /* in,out */ &funcBodyAstStmtList );
    createWrapperFunctionBodyTryCatchAst( wrappedFunctionNewName, /* in,out */ &funcBodyAstStmtList );

    return funcBodyAstStmtList;
}

ResultCode createWrapperFunctionAst( zend_ast_decl* originalFuncAstDecl, StringView wrappedFunctionNewName, /* out */ zend_ast_decl** pResult )
{
    ELASTIC_APM_ASSERT_VALID_PTR( originalFuncAstDecl );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pResult );

    ResultCode resultCode;
    zend_ast* originalFuncBodyAst = NULL;
    zend_ast_decl* clonedFuncDecl = NULL;
    uint32_t lineNumber = originalFuncAstDecl->end_lineno;

    originalFuncBodyAst = originalFuncAstDecl->child[ g_funcDeclBodyChildIndex ];
    // Temporarily set the body to NULL because we don't want to clone it
    // We restore it back after clone call
    originalFuncAstDecl->child[ g_funcDeclBodyChildIndex ] = NULL;
    resultCode = cloneAstTree( (zend_ast*)originalFuncAstDecl, lineNumber, /* out */ (zend_ast**)&clonedFuncDecl );
    originalFuncAstDecl->child[ g_funcDeclBodyChildIndex ] = originalFuncBodyAst;
    if ( resultCode != resultSuccess )
    {
        goto failure;
    }
    clonedFuncDecl->child[ g_funcDeclBodyChildIndex ] = createWrapperFunctionBodyAst( wrappedFunctionNewName, lineNumber );

    *pResult = clonedFuncDecl;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

uint32_t findAstDeclStartLineNumber( zend_ast_decl* astDecl )
{
    ZendAstPtrArrayView children = getAstDeclChildren( astDecl );
    uint32_t result = astDecl->start_lineno;
    ELASTIC_APM_FOR_EACH_INDEX( i, children.count )
    {
        zend_ast*  child = children.values[ i ];
        if ( ( child != NULL ) && ( zend_ast_get_lineno( child ) < result ) )
        {
            result = zend_ast_get_lineno( child );
        }
    }
    return result;
}

ResultCode wrapStandaloneFunctionAstWithPrePostHooks( /* in,out */ zend_ast_decl** pAstChildSlot )
{
    // Before:
    //
    //    function get_template() {
    //        // ...
    //    }
    //
    //    ZEND_AST_FUNC_DECL (name: get_template, line: 16, flags: 0, attr: 0, childCount: 4)                       <- original function under original name
    //
    // After:
    //
    //    { function get_templateElasticApmWrapped() {
    //        // ...
    //    } // fold-AST-into-one-line-begin
    //
    //    function get_template()
    //    {
    //        // wrapper function body
    //    }
    //    } // fold-AST-into-one-line-end
    //
    //    ZEND_AST_STMT_LIST (line: 16, attr: 0, childCount: 2)
    //        ZEND_AST_FUNC_DECL (name: get_templateElasticApmWrapped, line: 16, flags: 0, attr: 0, childCount: 4)  <- original function under wrapped name
    //        ZEND_AST_FUNC_DECL (name: get_template, line: 25, flags: 0, attr: 0, childCount: 4)                   <- wrapper function under original name

    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( pAstChildSlot );

    ResultCode resultCode;
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    String dbgCompiledFileName = stringIfNotNullElse( nullableZStringToStringView( CG(compiled_filename) ).begin, "<N/A>" );
    zend_ast_decl* originalFuncAstDecl = *pAstChildSlot; // it's not created by this function, so we should NOT clean it on failure
    StringBuffer wrappedFunctionNewName = ELASTIC_APM_EMPTY_STRING_BUFFER;
    zend_ast_decl* wrapperFuncAst = NULL;

    ELASTIC_APM_ASSERT( originalFuncAstDecl->kind == ZEND_AST_FUNC_DECL, "originalFuncAstDecl->kind: %s", streamZendAstKind( originalFuncAstDecl->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    StringView originalFuncName;
    if ( ! getAstDeclName( originalFuncAstDecl, /* out */ &originalFuncName ) )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to get function name - returning failure" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "originalFuncName: %s, compiled_filename: %s", originalFuncName.begin, dbgCompiledFileName );
    debugDumpAstTreeToLog( (zend_ast*) ( *pAstChildSlot ), logLevel_debug );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( createWrappedFunctionNewName( originalFuncName, /* out */ &wrappedFunctionNewName ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( createWrapperFunctionAst( originalFuncAstDecl, stringBufferToView( wrappedFunctionNewName ), /* out */ &wrapperFuncAst ) );
    zend_ast* newCombinedAst = createAstListWithTwoChildren( ZEND_AST_STMT_LIST, (zend_ast*)originalFuncAstDecl, (zend_ast*)wrapperFuncAst );
    newCombinedAst->lineno = findAstDeclStartLineNumber( originalFuncAstDecl );
    originalFuncAstDecl->name = createZStringForAst( stringBufferToView( wrappedFunctionNewName ) );
    *((zend_ast**)pAstChildSlot) = newCombinedAst;
    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ wrappedFunctionNewName );
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG( "originalFuncName: %s, compiled_filename: %s", originalFuncName.begin, dbgCompiledFileName );
    debugDumpAstTreeToLog( (zend_ast*) ( *pAstChildSlot ), logLevel_debug );
    return resultCode;

    failure:
    goto finally;
}

bool getAstName( zend_ast* ast, /* out */ StringView* name )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    switch ( ast->kind )
    {
        case ZEND_AST_CLASS:
        case ZEND_AST_FUNC_DECL:
        case ZEND_AST_METHOD:
            return getAstDeclName( (zend_ast_decl*)ast, /* out */ name );

        default:
            ELASTIC_APM_ASSERT( false, "Unexpected ast->kind: %s", streamZendAstKind( ast->kind, &txtOutStream ) );
            return false;
    }
}

bool parseAstNamespace( zend_ast* astNamespace, /* out */ StringView* pName, /* out */ zend_ast** pEnclosedScope )
{
    //    PHP code:
    //
    //            namespace // global
    //            {
    //            }
    //
    //    AST:
    //
    //            ZEND_AST_NAMESPACE (line: 4, attr: 0, childCount: 2)
    //                NULL
    //                ZEND_AST_STMT_LIST (line: 4, attr: 0, childCount: 5)

    //    PHP code:
    //
    //            namespace MyNamespace;
    //
    //    AST:
    //
    //            ZEND_AST_NAMESPACE (line: 3, attr: 0, childCount: 2)
    //                ZEND_AST_ZVAL (line: 3, attr: 0) [type: string, value: MyNamespace]
    //                NULL
    //
    //    PHP code:
    //
    //            namespace MyNamespace
    //            {
    //            }
    //
    //    AST:
    //
    //            ZEND_AST_NAMESPACE (line: 3, attr: 0, childCount: 2)
    //                ZEND_AST_ZVAL (line: 3, attr: 0) [type: string, value: MyNamespace]
    //                ZEND_AST_STMT_LIST (line: 4, attr: 0, childCount: 5)


    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    zend_ast* nameAstZval = NULL;
    zend_ast* enclosedScopeAst = NULL;
    StringView name;

    ELASTIC_APM_ASSERT( astNamespace->kind == ZEND_AST_NAMESPACE, "ast->kind: %s", streamZendAstKind( astNamespace->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );
    ELASTIC_APM_ASSERT_VALID_PTR( pName );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pEnclosedScope );

    uint32_t childrenCountAstNamespace = zend_ast_get_num_children( astNamespace );
    if ( childrenCountAstNamespace < 2 )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - childrenCountAstNamespace: %u", (unsigned)childrenCountAstNamespace );
        return false;
    }

    nameAstZval = astNamespace->child[ 0 ];
    if ( nameAstZval == NULL )
    {
        name = ELASTIC_APM_EMPTY_STRING_VIEW;
    }
    else if ( ! getStringFromAstZVal( nameAstZval, /* out */ &name ) )
    {
        return false;
    }

    enclosedScopeAst = astNamespace->child[ 1 ];
    if ( ( enclosedScopeAst != NULL ) && ( enclosedScopeAst->kind != ZEND_AST_STMT_LIST ) )
    {
        ELASTIC_APM_LOG_TRACE( "Returning false - enclosedScopeAst->kind: %s", streamZendAstKind( enclosedScopeAst->kind, &txtOutStream ) );
        return false;
    }

    *pName = name;
    *pEnclosedScope = enclosedScopeAst;
    ELASTIC_APM_LOG_TRACE( "Returning true - name [length: %"PRIu64"]: %.*s", (UInt64)(pName->length), (int)(pName->length), pName->begin );
    return true;
}

typedef bool (* CheckFindAstReqs)( zend_ast* ast, void* ctx );

bool checkFunctionReqs( zend_ast* ast, void* ctx )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTIC_APM_ASSERT( ast->kind == ZEND_AST_FUNC_DECL || ast->kind == ZEND_AST_METHOD, "ast->kind: %s", streamZendAstKind( ast->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    size_t minParamsCount = *(size_t*)ctx;

    ZendAstPtrArrayView paramsAsts;
    if ( ! getAstFunctionParameters( (zend_ast_decl*)ast, /* out */ &paramsAsts ) )
    {
        return false;
    }

    return paramsAsts.count >= minParamsCount;
}

bool findAstOfKindCheckNode( zend_ast* ast, zend_ast_kind kindToFind, StringView name, CheckFindAstReqs checkFindAstReqs, void* checkFindAstReqsCtx )
{
    if ( ast->kind != kindToFind )
    {
        return false;
    }

    if ( name.begin != NULL )
    {
        StringView astName;
        if ( ! ( getAstName( ast, /* out */ &astName ) && areStringViewsEqual( astName, name ) ) )
        {
            return false;
        }
    }

    return ( checkFindAstReqs == NULL ) || checkFindAstReqs( ast, checkFindAstReqsCtx );
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
zend_ast** findChildSlotAstByKind( zend_ast* ast, zend_ast_kind kindToFind, StringView namespace, StringView name, CheckFindAstReqs checkFindAstReqs, void* checkFindAstReqsCtx )
{
    if ( ! zend_ast_is_list( ast ) )
    {
        return NULL;
    }

    zend_ast_list* astAsList = zend_ast_get_list( ast );
    ELASTIC_APM_FOR_EACH_INDEX( i, astAsList->children )
    {
        zend_ast* child = astAsList->child[ i ];
        if ( zend_ast_is_list( child ) )
        {
            zend_ast** foundAst = findChildSlotAstByKind( child, kindToFind, namespace, name, checkFindAstReqs, checkFindAstReqsCtx );
            if ( foundAst != NULL )
            {
                return foundAst;
            }
            continue;
        }

        if ( child->kind == ZEND_AST_NAMESPACE )
        {
            StringView foundNamespaceName;
            zend_ast* namespaceEnclosedScope = NULL;
            if ( ! parseAstNamespace( child, /* out */ &foundNamespaceName, /* out */ &namespaceEnclosedScope ) )
            {
                continue;
            }

            if ( ! areStringViewsEqual( foundNamespaceName, namespace ) )
            {
                continue;
            }

            if ( namespaceEnclosedScope != NULL )
            {
                zend_ast** foundAst = findChildSlotAstByKind( namespaceEnclosedScope, kindToFind, namespace, name, checkFindAstReqs, checkFindAstReqsCtx );
                if ( foundAst != NULL )
                {
                    return foundAst;
                }
                continue;
            }
        }

        if ( findAstOfKindCheckNode( astAsList->child[ i ], kindToFind, name, checkFindAstReqs, checkFindAstReqsCtx ) )
        {
            // It's not &child on purpose since child is a local variable
            return &( astAsList->child[ i ] );
        }
    }

    return NULL;
}
#pragma clang diagnostic pop

zend_ast_decl** findChildSlotForStandaloneFunctionAst( zend_ast* rootAst, StringView namespace, StringView funcName, size_t minParamsCount )
{
    return (zend_ast_decl**) findChildSlotAstByKind( rootAst, ZEND_AST_FUNC_DECL, namespace, funcName, checkFunctionReqs, &minParamsCount );
}

zend_ast_decl* findClassAst( zend_ast* rootAst, StringView namespace, StringView className )
{
    zend_ast** result = findChildSlotAstByKind( rootAst, ZEND_AST_CLASS, namespace, className, /* checkFuncDeclReqs */ NULL, /* checkFindAstReqsCtx */ NULL );
    return (zend_ast_decl*)(*result);
}

zend_ast_decl** findChildSlotForMethodAst( zend_ast_decl* astClass, StringView methodName, size_t minParamsCount )
{
    //    ZEND_AST_CLASS (name: WP_Hook, line: 6, flags: 32, attr: 0, childCount: 3)
    //        NULL
    //        ZEND_AST_NAME_LIST (line: 6, attr: 0, childCount: 2)
    //            ZEND_AST_ZVAL (line: 6, attr: 1) [type: string, value: Iterator]
    //            ZEND_AST_ZVAL (line: 6, attr: 1) [type: string, value: ArrayAccess]
    //        ZEND_AST_STMT_LIST (line: 6, attr: 0, childCount: 1)
    //            ZEND_AST_METHOD (name: add_filter, line: 7, flags: 1, attr: 0, childCount: 4)

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT( astClass->kind == ZEND_AST_CLASS, "ast->kind: %s", streamZendAstKind( astClass->kind, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    zend_ast_decl* astAsDecl = (zend_ast_decl*)astClass;

    // class body is always child[ 2 ] - see zend_compile_class_decl
    return (zend_ast_decl**) findChildSlotAstByKind( astAsDecl->child[ 2 ], ZEND_AST_METHOD, /* namespace */ ELASTIC_APM_EMPTY_STRING_VIEW, methodName, checkFunctionReqs, &minParamsCount );
}

void elasticApmTransformAstImpl( zend_ast* ast )
{
    StringView compiledFileFullPath = nullableZStringToStringView( CG( compiled_filename) );
    if ( compiledFileFullPath.begin == NULL )
    {
        return;
    }

    size_t fileIndex;
    if ( ! wordPressInstrumentationShouldTransformAstInFile( compiledFileFullPath, /* out */ &fileIndex ) )
    {
        return;
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "compiledFileFullPath: %s", compiledFileFullPath.begin );
    debugDumpAstTree( compiledFileFullPath, ast, /* isBeforeProcess */ true );

    wordPressInstrumentationTransformAst( fileIndex, compiledFileFullPath, ast );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "compiledFileFullPath: %s", compiledFileFullPath.begin );
    debugDumpAstTree( compiledFileFullPath, ast, /* isBeforeProcess */ false );
}

void elasticApmTransformAst( zend_ast* ast )
{
    ELASTIC_APM_ASSERT( g_isOriginalZendAstProcessSet, "g_originalZendAstProcess: %p", g_originalZendAstProcess );

    if ( ( ! g_isLoadingAgentPhpCode ) && ast != NULL )
    {
        elasticApmTransformAstImpl( ast );
    }

    if ( g_originalZendAstProcess != NULL )
    {
        g_originalZendAstProcess( ast );
    }
}

void astInstrumentationOnModuleInit( const ConfigSnapshot* config )
{
    if ( config->astProcessEnabled )
    {
        g_originalZendAstProcess = zend_ast_process;
        g_isOriginalZendAstProcessSet = true;
        zend_ast_process = elasticApmTransformAst;
        ELASTIC_APM_LOG_DEBUG( "Changed zend_ast_process: from %p to elasticApmTransformAst (%p)", g_originalZendAstProcess, elasticApmTransformAst );
    } else {
        ELASTIC_APM_LOG_DEBUG( "AST processing will be DISABLED because configuration option %s (astProcessEnabled) is set to false", ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_ENABLED );
    }
}

void astInstrumentationOnModuleShutdown()
{
    if ( g_isOriginalZendAstProcessSet )
    {
        zend_ast_process_t zendAstProcessBeforeRestore = zend_ast_process;
        zend_ast_process = g_originalZendAstProcess;
        g_originalZendAstProcess = NULL;
        g_isOriginalZendAstProcessSet = false;
        ELASTIC_APM_LOG_DEBUG( "Restored zend_ast_process: from %p (%s elasticApmTransformAst: %p) -> %p"
                               , zendAstProcessBeforeRestore, zendAstProcessBeforeRestore == elasticApmTransformAst ? "==" : "!=", elasticApmTransformAst, g_originalZendAstProcess );
    }
}

void astInstrumentationOnRequestInit( const ConfigSnapshot* config )
{
    astProcessDebugDumpOnRequestInit( config );
    wordPressInstrumentationOnRequestInit();
}

void astInstrumentationOnRequestShutdown()
{
    wordPressInstrumentationOnRequestShutdown();
    astProcessDebugDumpOnRequestShutdown();
}
