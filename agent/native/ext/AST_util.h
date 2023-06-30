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
#include <php_version.h>
#include "ArrayView.h"
#include "basic_macros.h"

typedef zend_ast* ZendAstPtr;
ELASTIC_APM_DECLARE_ARRAY_VIEW( ZendAstPtr, ZendAstPtrArrayView );

static inline
bool isAstDecl( zend_ast_kind kind )
{
    switch( kind )
    {
        case ZEND_AST_FUNC_DECL:
        case ZEND_AST_CLOSURE:
        case ZEND_AST_METHOD:
        case ZEND_AST_CLASS:
        // ZEND_AST_ARROW_FUNC was added in PHP 7.4
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 4, 0 )
        case ZEND_AST_ARROW_FUNC:
        #endif
            return true;

        default:
            return false;
    }
}

/**
 * zend_ast_decl.child is array of size
 *      4 before PHP v8.0.0
 *      5 from PHP v8.0.0
 *
 * @see zend_ast_decl and zend_ast_create_decl
 */
enum
{
    elasticApmZendAstDeclChildrenCount =
    #if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 0, 0 ) /* if PHP version before 8.0.0 */
    4
    #else
    5
    #endif
};

static inline
ZendAstPtrArrayView getAstDeclChildren( zend_ast_decl* astDecl )
{
    ELASTIC_APM_STATIC_ASSERT( ELASTIC_APM_STATIC_ARRAY_SIZE( astDecl->child ) == elasticApmZendAstDeclChildrenCount );
    return ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, elasticApmZendAstDeclChildrenCount, &( astDecl->child[ 0 ] ) );
}

static inline
ZendAstPtrArrayView getAstChildren( zend_ast* ast )
{
    /** @see enum zend_ast_copy */

    if ( zend_ast_is_list( ast ) )
    {
        zend_ast_list* astList = zend_ast_get_list( ast );
        return ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, (size_t)( astList->children ), &( astList->child[ 0 ] ) );
    }

    if ( isAstDecl( ast->kind ) )
    {
        return getAstDeclChildren( (zend_ast_decl*)ast );
    }

    switch ( ast->kind )
    {
        /**
         * special nodes
         *
         * @see enum _zend_ast_kind
         */
        case ZEND_AST_ZNODE:
        case ZEND_AST_ZVAL:
        // ZEND_AST_CONSTANT was added in PHP 7.3
        #if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version from 7.3.0 */
        case ZEND_AST_CONSTANT:
        #endif
            return ELASTIC_APM_MAKE_EMPTY_ARRAY_VIEW( ZendAstPtrArrayView );

        default:
            return ELASTIC_APM_MAKE_ARRAY_VIEW( ZendAstPtrArrayView, (size_t)zend_ast_get_num_children( ast ), &( ast->child[ 0 ] ) );
    }
}
