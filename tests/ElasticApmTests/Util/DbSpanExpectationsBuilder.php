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

namespace ElasticApmTests\Util;

class DbSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public const DEFAULT_SPAN_TYPE = 'db';
    public const DEFAULT_SPAN_ACTION = 'query';

    public function __construct(SpanExpectations $prototype)
    {
        parent::__construct($prototype);
    }

    public static function default(string $dbType, ?string $dbName): SpanExpectations
    {
        $result = new SpanExpectations();

        $result->type->setValue(self::DEFAULT_SPAN_TYPE);
        $result->subtype->setValue($dbType);
        $result->action->setValue(self::DEFAULT_SPAN_ACTION);

        $serviceDst = $dbName === null ? $dbType : ($dbType . '/' . $dbName);
        $result->context->destination->service->name->setValue($serviceDst);
        $result->context->destination->service->resource->setValue($serviceDst);
        $result->context->destination->service->type->setValue($dbType);

        $result->context->service->target->name->setValue($dbName);
        $result->context->service->target->type->setValue($dbType);

        return $result;
    }

    private static function buildNameFromStatement(string $statement): string
    {
        return $statement;
    }

    public function fromStatement(string $statement): SpanExpectations
    {
        $result = $this->startNew();
        $result->name->setValue(self::buildNameFromStatement($statement));
        $result->context->db->statement->setValue($statement);
        return $result;
    }
}
