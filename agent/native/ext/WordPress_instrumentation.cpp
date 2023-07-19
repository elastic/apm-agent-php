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

enum WordPressInstrumentationFileToTransformAstIndex
{
    wordPress_instrumentation_file_to_transform_AST_plugin_php,
    wordPress_instrumentation_file_to_transform_AST_class_wp_hook_php,
    wordPress_instrumentation_file_to_transform_AST_theme_php,

    number_of_WordPress_instrumentation_files_to_transform_AST
};

#define ELASTIC_APM_WP_INCLUDES_PREFIX "wp-includes/"

static StringView g_filesToTransformAstPathSuffix[ number_of_WordPress_instrumentation_files_to_transform_AST ] =
{
    [ wordPress_instrumentation_file_to_transform_AST_plugin_php ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_WP_INCLUDES_PREFIX "plugin.php" ),
    [ wordPress_instrumentation_file_to_transform_AST_class_wp_hook_php ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_WP_INCLUDES_PREFIX "class-wp-hook.php" ),
    [ wordPress_instrumentation_file_to_transform_AST_theme_php ] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_WP_INCLUDES_PREFIX "theme.php" )
};

#undef ELASTIC_APM_WP_INCLUDES_PREFIX

struct WordPressInstrumentationRequestScopedState
{
    bool isInFailedMode;
    bool seenFile[ number_of_WordPress_instrumentation_files_to_transform_AST ];
};
typedef struct WordPressInstrumentationRequestScopedState WordPressInstrumentationRequestScopedState;

static WordPressInstrumentationRequestScopedState g_wordPressInstrumentationRequestScopedState;

void wordPressInstrumentationSwitchToFailedMode( String dbgCalledFromFunc )
{
    if ( g_wordPressInstrumentationRequestScopedState.isInFailedMode )
    {
        return;
    }

    ELASTIC_APM_LOG_ERROR( "Switched to failed mode; dbgCalledFromFunc: %s", dbgCalledFromFunc );

    g_wordPressInstrumentationRequestScopedState.isInFailedMode = true;
}

void wordPressInstrumentationOnRequestInit()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    g_wordPressInstrumentationRequestScopedState.isInFailedMode = false;

    ELASTIC_APM_FOR_EACH_INDEX( i, number_of_WordPress_instrumentation_files_to_transform_AST )
    {
        g_wordPressInstrumentationRequestScopedState.seenFile[ i ] = false;
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void wordPressInstrumentationOnRequestShutdown()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

static
ResultCode insertPreHookForFunctionWithHookNameCallbackParams( zend_ast_decl* astDecl )
{
    // standalone function:
    //      function _wp_filter_build_unique_id( $hook_name, $callback, $priority )
    // class WP_Hook method
    //      public function add_filter( $hook_name, $callback, $priority, $accepted_args )
    ArgCaptureSpec argCaptureSpecArr[] = { /* capture $hook_name by value */ captureArgByValue, /* capture $callback by reference */ captureArgByRef };
    return insertAstForFunctionPreHook( astDecl, ELASTIC_APM_MAKE_ARRAY_VIEW_FROM_STATIC( ArgCaptureSpecArrayView, argCaptureSpecArr ) );
}

static StringView g_globalNamespace = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" );

static StringView g_wp_filter_build_unique_id_funcName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "_wp_filter_build_unique_id" );

ResultCode wordPressInstrumentationTransformFile_plugin_php( zend_ast* ast )
{
    ResultCode resultCode;
    zend_ast_decl** p_wp_filter_build_unique_id_astFuncDecl = NULL;
    StringView setReadyToWrapFilterCallbacksConstName;

    // function _wp_filter_build_unique_id( $hook_name, $callback, $priority )
    p_wp_filter_build_unique_id_astFuncDecl = findChildSlotForStandaloneFunctionAst( ast, g_globalNamespace, g_wp_filter_build_unique_id_funcName, /* minParamsCount */ 3 );

    if ( p_wp_filter_build_unique_id_astFuncDecl == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Function %s are not found", g_wp_filter_build_unique_id_funcName.begin );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( insertPreHookForFunctionWithHookNameCallbackParams( *p_wp_filter_build_unique_id_astFuncDecl ) );
    // It's important to record if we instrumented _wp_filter_build_unique_id successfully.
    // _wp_filter_build_unique_id instrumentation alone cannot make application work incorrectly
    // because it checks if $callback is an instance our WordPressFilterCallbackWrapper class before unwrapping it.
    // On the other hand add_filter instrumentation alone CAN make application work incorrectly
    // if _wp_filter_build_unique_id was not instrumented as well.
    // So we record if we instrumented _wp_filter_build_unique_id successfully
    // and PHP part wraps callbacks only if it sees the record that _wp_filter_build_unique_id was instrumented successfully.
    setReadyToWrapFilterCallbacksConstName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS_CONST_NAME );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( appendDirectCallToInstrumentation( p_wp_filter_build_unique_id_astFuncDecl, setReadyToWrapFilterCallbacksConstName ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

static StringView g_WP_Hook_className = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "WP_Hook" );
static StringView g_add_filter_methodName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "add_filter" );

ResultCode wordPressInstrumentationTransformFile_class_wp_hook_php( zend_ast* ast )
{
    ResultCode resultCode;
    zend_ast_decl* WP_Hook_astClassDecl = NULL;
    zend_ast_decl** p_add_filter_astMethod = NULL;

    WP_Hook_astClassDecl = findClassAst( ast, g_globalNamespace, g_WP_Hook_className );
    if ( WP_Hook_astClassDecl == NULL )
    {
        ELASTIC_APM_LOG_TRACE( "Class %s not found", g_WP_Hook_className.begin );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    // public function add_filter( $hook_name, $callback, $priority, $accepted_args )
    p_add_filter_astMethod = findChildSlotForMethodAst( WP_Hook_astClassDecl, g_add_filter_methodName, /* minParamsCount */ 4 );
    if ( p_add_filter_astMethod == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Method %s (in class %s) not found", g_add_filter_methodName.begin, g_WP_Hook_className.begin );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( insertPreHookForFunctionWithHookNameCallbackParams( *p_add_filter_astMethod ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

static StringView g_get_template_funcName = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "get_template" );

ResultCode wordPressInstrumentationTransformFile_theme_php( zend_ast* ast )
{
    ResultCode resultCode;
    zend_ast_decl** p_get_template_astFuncDecl = NULL;

    // function get_template()
    p_get_template_astFuncDecl = findChildSlotForStandaloneFunctionAst( ast, g_globalNamespace, g_get_template_funcName, /* minParamsCount */ 0 );

    if ( p_get_template_astFuncDecl == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Function %s was not found", g_get_template_funcName.begin );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( wrapStandaloneFunctionAstWithPrePostHooks( p_get_template_astFuncDecl ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

bool wordPressInstrumentationShouldTransformAstInFile( StringView compiledFileFullPath, size_t* pFileIndex )
{
    if ( g_wordPressInstrumentationRequestScopedState.isInFailedMode )
    {
        return false;
    }

    ELASTIC_APM_FOR_EACH_INDEX( i, number_of_WordPress_instrumentation_files_to_transform_AST )
    {
        if ( g_wordPressInstrumentationRequestScopedState.seenFile[ i ] )
        {
            continue;
        }

        if ( isStringViewSuffix( compiledFileFullPath, g_filesToTransformAstPathSuffix[ i ] ) )
        {
            *pFileIndex = i;
            return true;
        }
    }

    return false;
}

typedef ResultCode (* WordPressTransformAstForFileFunc )( zend_ast* ast );

static WordPressTransformAstForFileFunc g_transformAstForFileFuncs[ number_of_WordPress_instrumentation_files_to_transform_AST ] =
{
    [ wordPress_instrumentation_file_to_transform_AST_plugin_php ] = wordPressInstrumentationTransformFile_plugin_php,
    [ wordPress_instrumentation_file_to_transform_AST_class_wp_hook_php ] = wordPressInstrumentationTransformFile_class_wp_hook_php,
    [ wordPress_instrumentation_file_to_transform_AST_theme_php ] = wordPressInstrumentationTransformFile_theme_php
};

void wordPressInstrumentationTransformAst( size_t fileIndex, StringView compiledFileFullPath, zend_ast* ast )
{
    ELASTIC_APM_ASSERT_LT_UINT64( fileIndex, number_of_WordPress_instrumentation_files_to_transform_AST );
    ELASTIC_APM_ASSERT( ! g_wordPressInstrumentationRequestScopedState.seenFile[ fileIndex ], "fileIndex: %u, file: %s", (UInt)fileIndex, g_filesToTransformAstPathSuffix[ fileIndex ].begin );
    g_wordPressInstrumentationRequestScopedState.seenFile[ fileIndex ] = true;

    ELASTIC_APM_LOG_TRACE_FUNCTION_ENTRY_MSG( "compiledFileFullPath: %s", compiledFileFullPath.begin );

    ResultCode resultCode;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( g_transformAstForFileFuncs[ fileIndex ]( ast ) );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG();
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    wordPressInstrumentationSwitchToFailedMode( __FUNCTION__ );
    goto finally;
}
