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
