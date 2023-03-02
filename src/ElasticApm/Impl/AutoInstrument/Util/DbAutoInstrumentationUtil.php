<?php

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

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbAutoInstrumentationUtil
{
    use StaticClassTrait;

    public const PER_OBJECT_KEY_DB_TYPE = 'DB_type';
    public const PER_OBJECT_KEY_DB_NAME = 'DB_name';
    public const PER_OBJECT_KEY_DB_QUERY = 'DB_query';

    public static function beginDbSpan(
        ?string $className,
        string $funcName,
        string $dbType,
        ?string $dbName,
        ?string $statement
    ): SpanInterface {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            $statement ?? AutoInstrumentationUtil::buildSpanNameFromCall($className, $funcName),
            Constants::SPAN_TYPE_DB,
            $dbType /* <- subtype */,
            Constants::SPAN_ACTION_QUERY
        );

        $span->context()->db()->setStatement($statement);

        self::setServiceForDbSpan($span, $dbType, $dbName);

        return $span;
    }

    public static function setServiceForDbSpan(SpanInterface $span, string $dbType, ?string $dbName): void
    {
        $destinationServiceResource = $dbType;
        if ($dbName !== null && !TextUtil::isEmptyString($dbName)) {
            $destinationServiceResource .= '/' . $dbName;
        }
        $span->context()->destination()->setService($destinationServiceResource, $destinationServiceResource, $dbType);
        $span->context()->service()->target()->setName($dbName);
        $span->context()->service()->target()->setType($dbType);
    }
}
