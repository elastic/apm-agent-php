/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#pragma once

#include "basic_types.h"
#include "ResultCode.h"
#include "StringView.h"

struct RequestScoped
{
    StringView lastMetadataFromPhpPart;
};
typedef struct RequestScoped RequestScoped;

ResultCode constructRequestScoped( RequestScoped* requestScoped );
ResultCode saveMetadataFromPhpPart( RequestScoped* requestScoped, StringView serializedMetadata );
void destructRequestScoped( RequestScoped* requestScoped );

