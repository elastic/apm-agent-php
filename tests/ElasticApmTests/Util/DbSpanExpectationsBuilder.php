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

    /** @var string */
    private $dbType;

    /** @var ?string */
    private $dbName;

    public function __construct(string $dbType, ?string $dbName)
    {
        $this->dbType = $dbType;
        $this->dbName = $dbName;
    }

    /** @inheritDoc */
    public function startNew(): SpanExpectations
    {
        $result = new SpanExpectations();

        $result->type->setValue(self::DEFAULT_SPAN_TYPE);
        $result->subtype->setValue($this->dbType);
        $result->action->setValue(self::DEFAULT_SPAN_ACTION);

        $serviceDst = $this->dbName === null ? $this->dbType : ($this->dbType . '/' . $this->dbName);
        $result->setService(
            $this->dbType /* <- targetType */,
            $this->dbName /* <- targetName */,
            $serviceDst /* <- destinationName */,
            $serviceDst /* <- destinationResource */,
            $this->dbType /* <- destinationType */
        );

        return $result;
    }

    /** @inheritDoc */
    public function fromClassMethodNames(string $className, string $methodName, bool $isStatic = false): SpanExpectations
    {
        $result = parent::fromClassMethodNames($className, $methodName, $isStatic);
        $result->assumeNotNullContext()->db->setValue(null);
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
        $result->ensureNotNullContext()->ensureNotNullDb()->statement->setValue($statement);
        return $result;
    }
}
