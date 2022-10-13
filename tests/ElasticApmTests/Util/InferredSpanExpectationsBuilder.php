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

use Elastic\Apm\Impl\StackTraceFrame;

class InferredSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public const DEFAULT_SPAN_TYPE = 'inferred';

    public function __construct(SpanExpectations $prototype)
    {
        parent::__construct($prototype);
    }

    public static function default(): SpanExpectations
    {
        $result = new SpanExpectations();

        $result->type->setValue(self::DEFAULT_SPAN_TYPE);

        return $result;
    }

    /**
     * @param string            $className
     * @param string            $methodName
     * @param bool              $isStatic
     * @param StackTraceFrame[] $stackTrace
     * @param bool              $allowExpectedStackTraceToBePrefix
     *
     * @return SpanExpectations
     */
    public function fromClassMethodNamesAndStackTrace(
        string $className,
        string $methodName,
        bool $isStatic,
        array $stackTrace,
        bool $allowExpectedStackTraceToBePrefix
    ): SpanExpectations {
        $result = self::fromClassMethodNames($className, $methodName, $isStatic);
        $result->stackTrace = $stackTrace;
        $result->allowExpectedStackTraceToBePrefix = $allowExpectedStackTraceToBePrefix;
        return $result;
    }

    /**
     * @param string            $funcName
     * @param StackTraceFrame[] $stackTrace
     * @param bool              $allowExpectedStackTraceToBePrefix
     *
     * @return SpanExpectations
     */
    public function fromFuncNameAndStackTrace(
        string $funcName,
        array $stackTrace,
        bool $allowExpectedStackTraceToBePrefix
    ): SpanExpectations {
        $result = self::fromFuncName($funcName);
        $result->stackTrace = $stackTrace;
        $result->allowExpectedStackTraceToBePrefix = $allowExpectedStackTraceToBePrefix;
        return $result;
    }
}
