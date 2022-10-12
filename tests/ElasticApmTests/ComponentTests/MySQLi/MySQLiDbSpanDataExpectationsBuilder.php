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

namespace ElasticApmTests\ComponentTests\MySQLi;

use ElasticApmTests\Util\DbSpanExpectationsBuilder;
use ElasticApmTests\Util\SpanExpectations;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySQLiDbSpanDataExpectationsBuilder extends DbSpanExpectationsBuilder
{
    /** @var bool */
    private $isOOPApi;

    public function __construct(bool $isOOPApi, SpanExpectations $shared)
    {
        parent::__construct($shared);
        $this->isOOPApi = $isOOPApi;
    }

    private static function buildFuncName(string $className, string $methodName): string
    {
        return $className . '_' . $methodName;
    }

    /**
     * @param string $className
     * @param string $methodName
     * @param ?string $funcName
     *
     * @return SpanExpectations
     */
    public function fromNames(string $className, string $methodName, ?string $funcName = null): SpanExpectations
    {
        return $this->isOOPApi
            ? $this->fromClassMethodNames($className, $methodName)
            : $this->fromFuncName($funcName ?? self::buildFuncName($className, $methodName));
    }
}
