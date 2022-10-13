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

use Elastic\Apm\Impl\Util\StackTraceUtil;
use PHPUnit\Framework\TestCase;

/**
 * @extends ExpectationsBuilderBase<SpanExpectations>
 */
class SpanExpectationsBuilder extends ExpectationsBuilderBase
{
    public function __construct(SpanExpectations $prototype)
    {
        parent::__construct($prototype);
    }

    protected function startNew(): SpanExpectations
    {
        $result = new SpanExpectations();
        $this->copyFromPrototypeTo($result);
        return $result;
    }

    /**
     * @param string $className
     * @param string $methodName
     *
     * @return SpanExpectations
     */
    public function fromClassMethodNames(
        string $className,
        string $methodName,
        bool $isStatic = false
    ): SpanExpectations {
        $result = $this->startNew();
        $name = StackTraceUtil::convertClassAndMethodToFunctionName($className, $isStatic, $methodName);
        TestCase::assertNotNull($name);
        $result->name->setValue($name);
        return $result;
    }

    /**
     * @param string $funcName
     *
     * @return SpanExpectations
     */
    public function fromFuncName(string $funcName): SpanExpectations
    {
        $result = $this->startNew();
        $result->name->setValue($funcName);
        return $result;
    }
}
