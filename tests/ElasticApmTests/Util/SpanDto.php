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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\SpanToSendInterface;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use ElasticApmTests\Util\Deserialization\StacktraceDeserializer;
use PHPUnit\Framework\TestCase;

class SpanDto extends ExecutionSegmentDto
{
    /** @var string */
    public $parentId;

    /** @var string */
    public $transactionId;

    /** @var ?string */
    public $action = null;

    /** @var ?string */
    public $subtype = null;

    /** @var null|StackTraceFrame[] */
    public $stackTrace = null;

    /** @var ?SpanContextDto */
    public $context = null;

    /**
     * @param mixed $value
     *
     * @return self
     */
    public static function deserialize($value): self
    {
        $result = new self();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (parent::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'action':
                        $result->action = self::assertValidKeywordString($value);
                        return true;
                    case 'context':
                        $result->context = SpanContextDto::deserialize($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = self::assertValidId($value);
                        return true;
                    case 'stacktrace':
                        $result->stackTrace = StacktraceDeserializer::deserialize($value);
                        return true;
                    case 'subtype':
                        $result->subtype = self::assertValidKeywordString($value);
                        return true;
                    case 'transaction_id':
                        $result->transactionId = self::assertValidId($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    public function assertValid(): void
    {
        $this->assertMatches(new SpanExpectations());
    }

    public function assertMatches(SpanExpectations $expectations): void
    {
        parent::assertMatchesExecutionSegment($expectations);

        self::assertValidId($this->parentId);
        self::assertValidId($this->transactionId);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->action, $this->action);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->subtype, $this->subtype);
        if ($this->stackTrace === null) {
            TestCase::assertNull($expectations->stackTrace);
            TestCase::assertNull($expectations->allowExpectedStackTraceToBePrefix);
        } else {
            self::assertValidStacktrace($this->stackTrace);
            if ($expectations->stackTrace !== null) {
                TestCase::assertNotNull($expectations->allowExpectedStackTraceToBePrefix);
                self::assertStackTraceMatches(
                    $expectations->stackTrace,
                    $expectations->allowExpectedStackTraceToBePrefix,
                    $this->stackTrace,
                    [ClassNameUtil::fqToShort(get_class($this)) => $this]
                );
            }
        }
        SpanContextDto::assertNullableMatches($expectations->context, $this->context);
    }

    /**
     * @param StackTraceFrame[]    $expectedStackTrace
     * @param bool                 $allowExpectedStackTraceToBePrefix
     * @param StackTraceFrame[]    $actualStackTrace
     * @param array<string, mixed> $ctxOuter
     *
     * @return void
     */
    public static function assertStackTraceMatches(
        array $expectedStackTrace,
        bool $allowExpectedStackTraceToBePrefix,
        array $actualStackTrace,
        array $ctxOuter = []
    ): void {
        $ctxTop = array_merge(
            [
                'expectedStackTrace'                => $expectedStackTrace,
                'actualStackTrace'                  => $actualStackTrace,
                'allowExpectedStackTraceToBePrefix' => $allowExpectedStackTraceToBePrefix,
            ],
            $ctxOuter
        );
        $ctxTopStr = LoggableToString::convert($ctxTop);
        if ($allowExpectedStackTraceToBePrefix) {
            TestCase::assertGreaterThanOrEqual(count($expectedStackTrace), count($actualStackTrace), $ctxTopStr);
        } else {
            TestCase::assertSame(count($expectedStackTrace), count($actualStackTrace), $ctxTopStr);
        }
        $expectedStackTraceCount = count($expectedStackTrace);
        $actualStackTraceCount = count($actualStackTrace);
        foreach (RangeUtil::generateUpTo($expectedStackTraceCount) as $i) {
            $expectedApmFrame = get_object_vars($expectedStackTrace[$expectedStackTraceCount - $i - 1]);
            $actualApmFrame = get_object_vars($actualStackTrace[$actualStackTraceCount - $i - 1]);
            $ctxPerFrame = array_merge(
                [
                    'expectedApmFrame'                  => $expectedApmFrame,
                    'actualApmFrame'                    => $actualApmFrame,
                    '$expectedStackTraceCount - $i - 1' => $expectedStackTraceCount - $i - 1,
                    '$actualStackTraceCount - $i - 1'   => $actualStackTraceCount - $i - 1,
                ],
                $ctxTop
            );
            $ctxPerFrameStr = LoggableToString::convert($ctxPerFrame);
            TestCase::assertSame(count($expectedApmFrame), count($actualApmFrame), $ctxPerFrameStr);
            foreach ($expectedApmFrame as $expectedPropName => $expectedPropVal) {
                $ctxPerProp = LoggableToString::convert(
                    array_merge(
                        ['expectedPropName' => $expectedPropName, 'expectedPropVal' => $expectedPropVal],
                        $ctxPerFrame
                    )
                );
                TestCaseBase::assertSameValueInArray($expectedPropName, $expectedPropVal, $actualApmFrame, $ctxPerProp);
            }
        }
    }

    public function assertEquals(SpanToSendInterface $original): void
    {
        self::assertEqualOriginalAndDto($original, $this);
    }
}
