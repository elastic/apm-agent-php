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

#include <Zend/zend.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_ast.h>

#include "AST_debug.h"
#include "ConfigSnapshot.h"
#include "log.h"
#include <sys/stat.h>
#include <errno.h>
#include <php_version.h>
#include <zend_language_parser.h>
#include <zend_vm_opcodes.h>
#include "util.h"
#include "util_for_PHP.h"
#include "elastic_apm_alloc.h"
#include "AST_util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_AUTO_INSTRUMENT

static bool g_astProcessDebugDumpIsEnabled = false;
static bool g_astProcessDebugDumpConvertedBackToSource = false;
static StringBuffer g_astProcessDebugDumpForPathPrefix;
static StringBuffer g_astProcessDebugDumpOutDir;

String zendAstMagicConstAttrToString( zend_ast_attr attr )
{
    switch ( attr )
    {
        case T_DIR: return "__DIR__";
        case T_FILE: return "__FILE__";
        case T_LINE: return "__LINE__";
        case T_NS_C: return "__NAMESPACE__";
        case T_CLASS_C: return "__CLASS__";
        case T_TRAIT_C: return "__TRAIT__";
        case T_METHOD_C: return "__METHOD__";
        case T_FUNC_C: return "__FUNCTION__";
        default: return NULL;
    }
}

String streamZendAstMagicConstAttr( zend_ast_attr attr, TextOutputStream* txtOutStream )
{
    String asString = zendAstMagicConstAttrToString( attr );
    return asString == NULL ? streamPrintf( txtOutStream, "UNKNOWN (as int: %d)", (int)attr ) : asString;
}

String zendAstKindToString( zend_ast_kind kind )
{
#   define ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( enumMember ) \
        case enumMember: \
            return (#enumMember) \
    /**/

    // Up to date with PHP v8.2.3
    switch ( kind )
    {
        /**
         * zend_ast_kind enum values as of PHP 7.2
         */
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_AND );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARG_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARRAY );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARRAY_ELEM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_OP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_REF );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_BINARY_OP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_BREAK );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CALL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CAST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CATCH );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CATCH_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST_DECL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLONE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLOSURE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLOSURE_USES );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_COALESCE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONDITIONAL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_DECL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_ELEM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONTINUE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DECLARE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DIM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_DO_WHILE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ECHO );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EMPTY );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ENCAPS_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EXIT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_EXPR_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FOR );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FOREACH );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_FUNC_DECL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GLOBAL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GOTO );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GREATER );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GREATER_EQUAL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_GROUP_USE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_HALT_COMPILER );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_IF );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_IF_ELEM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_INCLUDE_OR_EVAL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_INSTANCEOF );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ISSET );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_LABEL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MAGIC_CONST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD_CALL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_METHOD_REFERENCE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAME_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAMESPACE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NEW );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_OR );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PARAM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PARAM_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_POST_DEC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_POST_INC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRE_DEC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRE_INC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PRINT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_DECL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_ELEM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_REF );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_RETURN );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SHELL_EXEC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SILENCE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC_CALL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STATIC_PROP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_STMT_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH_CASE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_SWITCH_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_THROW );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_ADAPTATIONS );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_ALIAS );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRAIT_PRECEDENCE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TRY );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_MINUS );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_OP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNARY_PLUS );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNPACK );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_UNSET );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE_ELEM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_USE_TRAIT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_VAR );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_WHILE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_YIELD );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_YIELD_FROM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ZNODE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ZVAL );

        /**
         * values added in PHP 7.3
         */
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version from 7.3.0 */
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONSTANT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONSTANT_CLASS );
        #endif

        /**
         * values added in PHP 7.4
         */
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 4, 0 ) /* if PHP version from 7.4.0 */
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ARROW_FUNC );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ASSIGN_COALESCE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_NAME );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_PROP_GROUP );
        #endif

        /**
         * values added in PHP 8.0
         */
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 0, 0 ) /* if PHP version from 8.0.0 */
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE_GROUP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ATTRIBUTE_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CLASS_CONST_GROUP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH_ARM );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_MATCH_ARM_LIST );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NAMED_ARG );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NULLSAFE_METHOD_CALL );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_NULLSAFE_PROP );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE_UNION );
        #endif

        /**
         * values added in PHP 8.1
         */
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version from 8.1.0 */
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CALLABLE_CONVERT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_CONST_ENUM_INIT );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_ENUM_CASE );
        ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE( ZEND_AST_TYPE_INTERSECTION );
        #endif

        default:
            return NULL;
    }
#   undef ELASTIC_APM_GEN_ENUM_TO_STRING_SWITCH_CASE
}

String streamZendAstKind( zend_ast_kind kind, TextOutputStream* txtOutStream )
{
    String asString = zendAstKindToString( kind );
    return asString == NULL ? streamPrintf( txtOutStream, "UNKNOWN (as int: %d)", (int)kind ) : asString;
}

typedef void (* DebugDumpAstPrintLine )( void* ctx, String text, UInt nestingDepth );

struct DebugDumpAstPrinter
{
    DebugDumpAstPrintLine printLine;
    void* ctx;
};
typedef struct DebugDumpAstPrinter DebugDumpAstPrinter;

void debugDumpAst( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth );

void debugDumpAstPrintLineFormattedText( DebugDumpAstPrinter* printer, String text, UInt nestingDepth )
{
    printer->printLine( printer->ctx, text, nestingDepth );
}

UInt getAstLineNumber( zend_ast* ast )
{
    return (UInt) zend_ast_get_lineno( ast );
}

void debugDumpAstPrintLineForNull( DebugDumpAstPrinter* printer, UInt nestingDepth )
{
    debugDumpAstPrintLineFormattedText( printer, "NULL", nestingDepth );
}

void debugDumpAstPrintLineTemplate( DebugDumpAstPrinter* printer, zend_ast_kind kind, UInt lineNumber, String attrAsString, UInt childCount, String additionalInfo, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String text = ( additionalInfo == NULL )
            ? streamPrintf( &txtOutStream, "%s (line: %u, attr: %s, childCount: %u)"
                            , streamZendAstKind( kind, &txtOutStream ), lineNumber, attrAsString, childCount )
            : streamPrintf( &txtOutStream, "%s (line: %u, attr: %s, childCount: %u, %s)"
                            , streamZendAstKind( kind, &txtOutStream ), lineNumber, attrAsString, childCount, additionalInfo );

    debugDumpAstPrintLineFormattedText( printer, text, nestingDepth );
}

static inline
String streamAstAttribute( zend_ast_attr attr, TextOutputStream* txtOutStream )
{
    return streamPrintf( txtOutStream, "%u", attr );
}

void debugDumpAstPrintLineDefault( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String attrAsString = streamAstAttribute( ast->attr, &txtOutStream );

    debugDumpAstPrintLineTemplate( printer, ast->kind, getAstLineNumber( ast ), attrAsString, getAstChildren( ast ).count, /* additionalInfo */ NULL, nestingDepth );
}

size_t calcNumberOfNonWhiteChars( StringView strVw )
{
    size_t result = 0;
    ELASTIC_APM_FOR_EACH_INDEX( i, strVw.length )
    {
        if ( ! isWhiteSpace( strVw.begin[ i ] ) )
        {
            ++result;
        }
    }
    return result;
}

void debugDumpAstPrintLineForDecl( DebugDumpAstPrinter* printer, zend_ast_decl* astDecl, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String attrAsString = streamAstAttribute( astDecl->attr, &txtOutStream );
    size_t docCommentNumberOfNonWhiteChars = calcNumberOfNonWhiteChars( nullableZStringToStringView( astDecl->doc_comment ) );
    String additionalInfo = streamPrintf(
        &txtOutStream
        , "name: %s, end line: %u, flags: %u, doc_comment: %s"
        , nullableZStringToString( astDecl->name ), (UInt)( astDecl->start_lineno ), (UInt)( astDecl->flags )
        , astDecl->doc_comment == NULL ? "NULL" : streamPrintf( &txtOutStream, "[number of non-white chars: %u]", (unsigned)docCommentNumberOfNonWhiteChars )
    );

    debugDumpAstPrintLineTemplate( printer, astDecl->kind, astDecl->start_lineno, attrAsString, getAstChildren( (zend_ast*)astDecl ).count, additionalInfo, nestingDepth );
}

void debugDumpAstPrintLineForMagicConst( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String attrAsString = streamZendAstMagicConstAttr( ast->attr, &txtOutStream );

    debugDumpAstPrintLineTemplate( printer, ast->kind, getAstLineNumber( ast ), attrAsString, getAstChildren( ast ).count, /* additionalInfo */ NULL, nestingDepth );
}

String debugDumpAstZvalStreamVal( zend_ast* ast, TextOutputStream* txtOutStream )
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
            return streamPrintf( txtOutStream, "type: string, value: %.*s", (int)(strVw.length), strVw.begin );
        }

        case IS_LONG:
            return streamPrintf( txtOutStream, "type: long, value: %" PRId64, (Int64)(Z_LVAL_P( zVal )) );

        case IS_DOUBLE:
            return streamPrintf( txtOutStream, "type: double, value: %f", (double)(Z_DVAL_P( zVal )) );

        case IS_NULL:
            return streamPrintf( txtOutStream, "type: null" );

        case IS_FALSE:
            return streamPrintf( txtOutStream, "type: false" );
        case IS_TRUE:
            return streamPrintf( txtOutStream, "type: true " );

        default:
            return streamPrintf( txtOutStream, "type: %s (type ID as int: %d)", zend_get_type_by_const( zValType ), (int)zValType );
    }
}

void debugDumpAstPrintLineForZVal( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String attrAsString = streamAstAttribute( ast->attr, &txtOutStream );
    String additionalInfo = debugDumpAstZvalStreamVal( ast, &txtOutStream );

    debugDumpAstPrintLineTemplate( printer, ast->kind, getAstLineNumber( ast ), attrAsString, getAstChildren( ast ).count, additionalInfo, nestingDepth );
}

void debugDumpAstPrintLineForBinaryOp( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String attrAsString = streamPrintf( &txtOutStream, "opcode: %s (ID as int: %d)", zend_get_opcode_name( (zend_uchar)(ast->attr) ), (int)(ast->attr) );

    debugDumpAstPrintLineTemplate( printer, ast->kind, getAstLineNumber( ast ), attrAsString, getAstChildren( ast ).count, /* additionalInfo */ NULL, nestingDepth );
}

void debugDumpAstPrintLineDispatch( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    if ( isAstDecl( ast->kind ) )
    {
        debugDumpAstPrintLineForDecl( printer, (zend_ast_decl*)ast, nestingDepth );
        return;
    }

    switch ( ast->kind )
    {
        case ZEND_AST_BINARY_OP:
            debugDumpAstPrintLineForBinaryOp( printer, ast, nestingDepth );
            return;

        case ZEND_AST_MAGIC_CONST:
            debugDumpAstPrintLineForMagicConst( printer, ast, nestingDepth );
            return;

        case ZEND_AST_ZVAL:
            debugDumpAstPrintLineForZVal( printer, ast, nestingDepth );
            return;
        default:
            debugDumpAstPrintLineDefault( printer, ast, nestingDepth );
            return;
    }
}

#pragma clang diagnostic push
#pragma ide diagnostic ignored "misc-no-recursion"
void debugDumpAst( DebugDumpAstPrinter* printer, zend_ast* ast, UInt nestingDepth )
{
    if ( ast == NULL )
    {
        debugDumpAstPrintLineForNull( printer, nestingDepth );
        return;
    }

    debugDumpAstPrintLineDispatch( printer, ast, nestingDepth );

    ZendAstPtrArrayView children = getAstChildren( ast );
    ELASTIC_APM_FOR_EACH_INDEX( i, children.count )
    {
        debugDumpAst( printer, children.values[ i ], nestingDepth + 1 );
    }
}
#pragma clang diagnostic pop

struct DebugDumpAstPrintToLogCtx
{
    LogLevel logLevel;
};
typedef struct DebugDumpAstPrintToLogCtx DebugDumpAstPrintToLogCtx;

void debugDumpAstPrintLineToLog( void* ctx, String text, UInt nestingDepth )
{
    const DebugDumpAstPrintToLogCtx* const localCtx = (const DebugDumpAstPrintToLogCtx*)ctx;

    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_WITH_LEVEL( localCtx->logLevel, "%s%s", streamIndent( nestingDepth, &txtOutStream ), text );
}

void debugDumpAstTreeToLog( zend_ast* ast, LogLevel logLevel )
{
    if ( maxEnabledLogLevel() < logLevel )
    {
        return;
    }

    DebugDumpAstPrintToLogCtx ctx = (DebugDumpAstPrintToLogCtx){ .logLevel = logLevel };
    DebugDumpAstPrinter printer = (DebugDumpAstPrinter){ .printLine = &debugDumpAstPrintLineToLog, .ctx = &ctx };
    debugDumpAst( &printer, ast, /* nestingDepth */ 0 );
}


struct DebugDumpAstPrintToTextOutputStreamCtx
{
    TextOutputStream* txtOutStream;
    String result;
};
typedef struct DebugDumpAstPrintToTextOutputStreamCtx DebugDumpAstPrintToTextOutputStreamCtx;

void debugDumpAstPrintLineToTextOutputStream( void* ctx, String text, UInt nestingDepth )
{
    ELASTIC_APM_UNUSED( nestingDepth );

    DebugDumpAstPrintToTextOutputStreamCtx* const localCtx = (DebugDumpAstPrintToTextOutputStreamCtx*)ctx;

    localCtx->result = streamPrintf( localCtx->txtOutStream, "%s", text );
}

String streamZendAstNode( zend_ast* ast, TextOutputStream* txtOutStream )
{
    DebugDumpAstPrintToTextOutputStreamCtx ctx = (DebugDumpAstPrintToTextOutputStreamCtx){ .txtOutStream = txtOutStream, .result = NULL };
    DebugDumpAstPrinter printer = (DebugDumpAstPrinter){ .printLine = &debugDumpAstPrintLineToTextOutputStream, .ctx = &ctx };
    debugDumpAstPrintLineDispatch( &printer, ast, /* nestingDepth */ 0 );
    return ctx.result;
}

struct DebugDumpAstPrintToFileCtx
{
    FILE* outFile;
};
typedef struct DebugDumpAstPrintToFileCtx DebugDumpAstPrintToFileCtx;

void debugDumpAstPrintLineToFile( void* ctx, String text, UInt nestingDepth )
{
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    const DebugDumpAstPrintToFileCtx* const localCtx = (const DebugDumpAstPrintToFileCtx*)ctx;

    fputs( streamIndent( nestingDepth, &txtOutStream ), localCtx->outFile );
    fputs( text, localCtx->outFile );
    fputs( "\n", localCtx->outFile );
}

bool isFileSystemPathPrefix( StringView path, StringView pathPrefix )
{
    return isStringViewPrefix(
            path,
            pathPrefix,
            /* shouldIgnoreCase */
#       ifdef PHP_WIN32
            true
#       else // #ifdef PHP_WIN32
            false
#       endif // #ifdef PHP_WIN32
    );
}

ResultCode ensureDirectoryExists( String dirFullPath )
{
#   ifdef PHP_WIN32

    return resultFailure;

#   else // #ifdef PHP_WIN32

    int mkdirRetVal = mkdir( dirFullPath, /* mode */ S_IRWXU | S_IRWXG | S_IROTH | S_IXOTH );
    if ( mkdirRetVal != 0 )
    {
        int errnoValue = errno;
        if (errnoValue == EEXIST)
        {
            return resultSuccess;
        }
        char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR( "mkdir failed; dirFullPath: `%s', mkdirRetVal: %d, errno: %d (%s)", dirFullPath, mkdirRetVal, errnoValue, streamErrNo( errnoValue, &txtOutStream ) );
        return resultFailure;
    }
    return resultSuccess;

#   endif // #ifdef PHP_WIN32
}

ResultCode ensureDirectoriesExist( StringView fullPath )
{
    ELASTIC_APM_ASSERT( ! isEmptyStringView( fullPath ), "fullPath should not be empty" );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "fullPath: %s", fullPath.begin );

    ResultCode resultCode;
    StringBuffer dirFullPath = ELASTIC_APM_EMPTY_STRING_BUFFER;
    size_t dirFullPathLen = 0;

    char directorySeparator =
#       ifdef PHP_WIN32
            '\\';
#       else // #ifdef PHP_WIN32
            '/';
#       endif // #ifdef PHP_WIN32

    ELASTIC_APM_MALLOC_STRING_BUFFER_IF_FAILED_GOTO( /* maxLength */ fullPath.length, /* out */ dirFullPath );
    dirFullPath.begin[ 0 ] = '\0';
    ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( /* suffixToAppend */ fullPath, dirFullPath, /* in,out */ &dirFullPathLen ) );
    ELASTIC_APM_ASSERT_EQ_UINT64( dirFullPathLen, fullPath.length );


    for ( MutableString begin = &( dirFullPath.begin[ 0 ] ), current = begin + 1, end = begin + fullPath.length ; current != end ; )
    {
        char* pDirSep = strchr( current, directorySeparator );
        if ( pDirSep == NULL )
        {
            break;
        }

        char savedDirSep = *pDirSep;
        *pDirSep = '\0';
        ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureDirectoryExists( dirFullPath.begin ) );
        *pDirSep = savedDirSep;
        current = pDirSep + 1;
    }

    resultCode = resultSuccess;
    finally:

    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ dirFullPath );

    ELASTIC_APM_UNUSED( resultCode );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "fullPath: %s", fullPath.begin );
    return resultCode;

    failure:
    goto finally;
}

ResultCode buildFileFullPath( StringViewArrayView pathParts, /* out */ StringBuffer* pResult )
{
    ResultCode resultCode;
    StringBuffer result = ELASTIC_APM_EMPTY_STRING_BUFFER;
    size_t length = 0;
    size_t contentLength = 0;

    ELASTIC_APM_FOR_EACH_INDEX( i, pathParts.count )
    {
        length += pathParts.values[ i ].length;
    }

    ELASTIC_APM_MALLOC_STRING_BUFFER_IF_FAILED_GOTO( /* maxLength */ length, /* out */ result );
    result.begin[ 0 ] = '\0';

    ELASTIC_APM_FOR_EACH_INDEX( i, pathParts.count )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( /* suffixToAppend */ pathParts.values[ i ], result, /* in,out */ &contentLength ) );
    }
    ELASTIC_APM_ASSERT_EQ_UINT64( contentLength, length );

    *pResult = result;
    result = ELASTIC_APM_EMPTY_STRING_BUFFER;
    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ result );
    goto finally;
}

void debugDumpAstSubTreeConvertedBackToSource( StringView compiledFileFullPath, StringView compiledFileRelativePath, zend_ast* ast, StringView isBeforeProcessSuffix, TextOutputStream* txtOutStream )
{
    ResultCode resultCode;
    FILE* convertedBackToSourceFile = NULL;
    int errnoValue = 0;
    StringBuffer convertedBackToSourceFileFullPath = ELASTIC_APM_EMPTY_STRING_BUFFER;
    zend_string* convertedBackToSourceText = NULL;
    String textAsCString;

    StringView convertedBackToSourceFileExtensionSuffix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ".php" );
    StringView convertedBackToSourceFileFullPathParts[] = {
        stringBufferToView( g_astProcessDebugDumpOutDir ),
        compiledFileRelativePath,
        isBeforeProcessSuffix,
        ELASTIC_APM_STRING_LITERAL_TO_VIEW( ".converted_back_to_source" ),
        convertedBackToSourceFileExtensionSuffix
    };
    ELASTIC_APM_CALL_IF_FAILED_GOTO( buildFileFullPath( ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( StringViewArrayView, convertedBackToSourceFileFullPathParts ), /* out */ &convertedBackToSourceFileFullPath ) );

    errnoValue = openFile( convertedBackToSourceFileFullPath.begin, "w", /* out */ &convertedBackToSourceFile );
    if ( errnoValue != 0 )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to open file; convertedBackToSourceFileFullPath: %s; errno: %d (%s)", convertedBackToSourceFileFullPath.begin, errnoValue, streamErrNo( errnoValue, txtOutStream ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_LOG_INFO( "Printing AST converted back to source of %s to %s ...", compiledFileFullPath.begin, convertedBackToSourceFileFullPath.begin );
    convertedBackToSourceText = zend_ast_export( /* prefix */ "", ast, /* suffix */ "" );
    textAsCString = nullableZStringToString( convertedBackToSourceText );
    if ( textAsCString == NULL )
    {
        ELASTIC_APM_LOG_INFO( "Nothing to print for AST of %s converted back to source to %s", compiledFileFullPath.begin, convertedBackToSourceFileFullPath.begin );
    }
    else
    {
        fputs( textAsCString, convertedBackToSourceFile );
        ELASTIC_APM_LOG_INFO( "Printed AST converted back to source of %s to %s. Contents:\n%s"
                              , compiledFileFullPath.begin, convertedBackToSourceFileFullPath.begin, nullableZStringToString( convertedBackToSourceText ) );
    }

    resultCode = resultSuccess;
    finally:
    if ( convertedBackToSourceFile != NULL )
    {
        fclose( convertedBackToSourceFile );
        convertedBackToSourceFile = NULL;
    }
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ convertedBackToSourceFileFullPath );
    if ( convertedBackToSourceText != NULL )
    {
        zend_string_release( convertedBackToSourceText );
        convertedBackToSourceText = NULL;
    }

    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void debugDumpAstSubTreeToFile( StringView compiledFileFullPath, zend_ast* ast, bool isBeforeProcess )
{
    ResultCode resultCode;
    StringBuffer debugDumpFileFullPath = ELASTIC_APM_EMPTY_STRING_BUFFER;
    FILE* debugDumpFile = NULL;
    int errnoValue = 0;
    char txtOutStreamBuf[ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    DebugDumpAstPrintToFileCtx ctx;
    DebugDumpAstPrinter printer;


    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "compiledFileFullPath: %s", compiledFileFullPath.begin );

    StringView pathPrefix = stringBufferToView( g_astProcessDebugDumpForPathPrefix );
    if ( ! isFileSystemPathPrefix( compiledFileFullPath, pathPrefix ) )
    {
        ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "Skipping this file because it does not have required prefix: %s", pathPrefix.begin );
        return;
    }

    StringView compiledFileRelativePath = subStringView( compiledFileFullPath, pathPrefix.length );
    StringView isBeforeProcessSuffix = isBeforeProcess ? ELASTIC_APM_STRING_LITERAL_TO_VIEW( ".before_AST_process" ) : ELASTIC_APM_STRING_LITERAL_TO_VIEW( ".after_AST_process" );
    StringView debugDumpFileExtensionSuffix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ".txt" );
    StringView debugDumpFileFullPathParts[] = {
        stringBufferToView( g_astProcessDebugDumpOutDir ),
        compiledFileRelativePath,
        isBeforeProcessSuffix,
        debugDumpFileExtensionSuffix
    };
    ELASTIC_APM_CALL_IF_FAILED_GOTO( buildFileFullPath( ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( StringViewArrayView, debugDumpFileFullPathParts ), /* out */ &debugDumpFileFullPath ) );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureDirectoriesExist( stringBufferToView( debugDumpFileFullPath ) ) );

    errnoValue = openFile( debugDumpFileFullPath.begin, "w", /* out */ &debugDumpFile );
    if ( errnoValue != 0 )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to open file; debugDumpFileFullPath: %s; errno: %d (%s)", debugDumpFileFullPath.begin, errnoValue, streamErrNo( errnoValue, &txtOutStream ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_LOG_INFO( "Printing AST debug dump of %s to %s ...", compiledFileFullPath.begin, debugDumpFileFullPath.begin );
    ctx = (DebugDumpAstPrintToFileCtx){ .outFile = debugDumpFile };
    printer = (DebugDumpAstPrinter){ .printLine = &debugDumpAstPrintLineToFile, .ctx = &ctx };
    debugDumpAst( &printer, ast, /* nestingDepth */ 0 );
    ELASTIC_APM_LOG_INFO( "Printed AST debug dump of %s to %s", compiledFileFullPath.begin, debugDumpFileFullPath.begin );

    if ( g_astProcessDebugDumpConvertedBackToSource )
    {
        debugDumpAstSubTreeConvertedBackToSource( compiledFileFullPath, compiledFileRelativePath, ast, isBeforeProcessSuffix, &txtOutStream );
    }

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG();
    if ( debugDumpFile != NULL )
    {
        fclose( debugDumpFile );
        debugDumpFile = NULL;
    }
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ debugDumpFileFullPath );

    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void debugDumpAstTree( StringView compiledFileFullPath, zend_ast* ast, bool isBeforeProcess )
{
    LogLevel logLevel = g_astProcessDebugDumpIsEnabled ? logLevel_debug : logLevel_trace;
    ELASTIC_APM_LOG_FUNCTION_ENTRY_MSG_WITH_LEVEL( logLevel, "compiledFileFullPath: %s, isBeforeProcess: %s, g_astProcessDebugDumpIsEnabled: %s"
                                                   , compiledFileFullPath.begin, boolToString( isBeforeProcess ), boolToString( g_astProcessDebugDumpIsEnabled ) );

    debugDumpAstTreeToLog( ast, g_astProcessDebugDumpIsEnabled ? logLevel_debug : logLevel_trace );

    if ( g_astProcessDebugDumpIsEnabled )
    {
        debugDumpAstSubTreeToFile( compiledFileFullPath, ast, isBeforeProcess );
    }

    ELASTIC_APM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( logLevel, "compiledFileFullPath: %s, isBeforeProcess: %s, g_astProcessDebugDumpIsEnabled: %s"
                                                  , compiledFileFullPath.begin, boolToString( isBeforeProcess ), boolToString( g_astProcessDebugDumpIsEnabled ) );
}

StringView directorySeparatorAsStringView()
{
    return ELASTIC_APM_STRING_LITERAL_TO_VIEW(
#       ifdef PHP_WIN32
            "\\"
#       else // #ifdef PHP_WIN32
            "/"
#       endif // #ifdef PHP_WIN32
    );
}

ResultCode ensureTrailingDirectorySeparator( StringView inPath, /* out */ StringBuffer* result )
{
    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "inPath: %s", inPath.begin );

    ResultCode resultCode;
    StringBuffer outPathBuf = ELASTIC_APM_EMPTY_STRING_BUFFER;
    size_t outPathLen = 0;
    size_t outPathMaxLen = inPath.length + 1;
    StringView directorySeparator = directorySeparatorAsStringView();

    ELASTIC_APM_MALLOC_STRING_BUFFER_IF_FAILED_GOTO( /* maxLength */ outPathMaxLen, /* out */ outPathBuf );
    outPathBuf.begin[ 0 ] = '\0';

    ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( /* suffixToAppend */ inPath, outPathBuf, /* in,out */ &outPathLen ) );
    if ( ! isStringViewSuffix( inPath, directorySeparator ) )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( appendToStringBuffer( /* suffixToAppend */ directorySeparator, outPathBuf, /* in,out */ &outPathLen ) );
    }

    *result = outPathBuf;
    outPathBuf = ELASTIC_APM_EMPTY_STRING_BUFFER;
    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG( "inPath: %s", inPath.begin );
    return resultCode;

    failure:
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ outPathBuf );
    goto finally;

#undef ELASTIC_APM_DIRECTORY_SEPARATOR
}

void astProcessDebugDumpOnRequestInit( const ConfigSnapshot* config )
{
    ResultCode resultCode;

    if ( config->astProcessDebugDumpOutDir == NULL )
    {
        return;
    }

    StringView pathPrefix = config->astProcessDebugDumpForPathPrefix == NULL ? ELASTIC_APM_EMPTY_STRING_VIEW : stringToView( config->astProcessDebugDumpForPathPrefix );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureTrailingDirectorySeparator( pathPrefix, /* out */ &g_astProcessDebugDumpForPathPrefix ) );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureTrailingDirectorySeparator( stringToView( config->astProcessDebugDumpOutDir ), /* out */ &g_astProcessDebugDumpOutDir ) );

    g_astProcessDebugDumpConvertedBackToSource = config->astProcessDebugDumpConvertedBackToSource;
    g_astProcessDebugDumpIsEnabled = true;
    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

void astProcessDebugDumpOnRequestShutdown()
{
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ g_astProcessDebugDumpOutDir );
    ELASTIC_APM_FREE_STRING_BUFFER_AND_SET_TO_NULL( /* in,out */ g_astProcessDebugDumpForPathPrefix );
    g_astProcessDebugDumpConvertedBackToSource = false;
    g_astProcessDebugDumpIsEnabled = false;
}
