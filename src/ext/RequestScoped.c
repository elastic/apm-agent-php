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
#include "elastic_apm_assert.h"
#include "elastic_apm_alloc.h"

ResultCode constructRequestScoped( RequestScoped* requestScoped )
{
    ELASTIC_APM_ZERO_STRUCT( requestScoped );

    ELASTIC_APM_ASSERT_VALID_PTR( requestScoped );

    return resultSuccess;
}

void destructRequestScoped( RequestScoped* requestScoped )
{
    ELASTIC_APM_ASSERT_VALID_PTR( requestScoped );

    ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( requestScoped->lastMetadataFromPhpPart.length + 1, requestScoped->lastMetadataFromPhpPart.begin );
    requestScoped->lastMetadataFromPhpPart = makeEmptyStringView();

    ELASTIC_APM_ZERO_STRUCT( requestScoped );
}

ResultCode saveMetadataFromPhpPart( RequestScoped* requestScoped, StringView metadataFromPhpPart )
{
    ELASTIC_APM_ASSERT_VALID_PTR( requestScoped );

    ResultCode resultCode;
    StringView metadataCopy = makeEmptyStringView();

    ELASTIC_APM_EMALLOC_DUP_STRING_IF_FAILED_GOTO( metadataFromPhpPart.begin, metadataCopy.begin );
    metadataCopy.length = metadataFromPhpPart.length;

    resultCode = resultSuccess;

    ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( requestScoped->lastMetadataFromPhpPart.length + 1, requestScoped->lastMetadataFromPhpPart.begin );
    requestScoped->lastMetadataFromPhpPart = metadataCopy;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_EFREE_STRING_AND_SET_TO_NULL( metadataCopy.length + 1, metadataCopy.begin );
    goto finally;
}
