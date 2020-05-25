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

#include "RequestScoped.h"
#include "basic_macros.h"
#include "elasticapm_assert.h"
#include "elasticapm_alloc.h"

ResultCode constructRequestScoped( RequestScoped* requestScoped )
{
    ELASTICAPM_ZERO_STRUCT( requestScoped );

    ELASTICAPM_ASSERT_VALID_PTR( requestScoped );

    return resultSuccess;
}

void destructRequestScoped( RequestScoped* requestScoped )
{
    ELASTICAPM_ASSERT_VALID_PTR( requestScoped );

    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( requestScoped->lastMetadataFromPhpPart.length + 1, requestScoped->lastMetadataFromPhpPart.begin );
    requestScoped->lastMetadataFromPhpPart = makeEmptyStringView();

    ELASTICAPM_ZERO_STRUCT( requestScoped );
}

ResultCode saveMetadataFromPhpPart( RequestScoped* requestScoped, StringView metadataFromPhpPart )
{
    ELASTICAPM_ASSERT_VALID_PTR( requestScoped );

    ResultCode resultCode;
    StringView metadataCopy = makeEmptyStringView();

    ELASTICAPM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( metadataFromPhpPart.begin, metadataCopy.begin );
    metadataCopy.length = metadataFromPhpPart.length;

    resultCode = resultSuccess;

    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( requestScoped->lastMetadataFromPhpPart.length + 1, requestScoped->lastMetadataFromPhpPart.begin );
    requestScoped->lastMetadataFromPhpPart = metadataCopy;

    finally:
    return resultCode;

    failure:
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( metadataCopy.length + 1, metadataCopy.begin );
    goto finally;
}
