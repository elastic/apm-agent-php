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

use Elastic\Apm\Impl\SpanToSendInterface;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use ElasticApmTests\Util\Deserialization\StacktraceDeserializer;

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

    /** @var ?SpanCompositeDto */
    public $composite = null;

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
                    case 'composite':
                        $result->composite = SpanCompositeDto::deserialize($value);
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
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        parent::assertMatchesExecutionSegment($expectations);

        self::assertValidId($this->parentId);
        TestCaseBase::assertSameExpectedOptional($expectations->parentId, $this->parentId);
        self::assertValidId($this->transactionId);
        TestCaseBase::assertSameExpectedOptional($expectations->transactionId, $this->transactionId);

        self::assertSameNullableKeywordStringExpectedOptional($expectations->action, $this->action);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->subtype, $this->subtype);
        if ($this->stackTrace === null) {
            if ($expectations->stackTrace->isValueSet()) {
                TestCaseBase::assertNull($expectations->stackTrace->getValue());
            }
        } else {
            self::assertValidStacktrace($this->stackTrace);
            if ($expectations->stackTrace->isValueSet()) {
                $stackTraceExpectations = $expectations->stackTrace->getValue();
                TestCaseBase::assertNotNull($stackTraceExpectations);
                self::assertStackTraceMatches($stackTraceExpectations, $this->stackTrace);
            }
        }

        SpanCompositeExpectations::assertNullableMatches($expectations->composite, $this->composite);
        SpanContextExpectations::assertNullableMatches($expectations->context, $this->context);
    }

    /**
     * @param StackTraceExpectations $expectations
     * @param StackTraceFrame[]      $actual
     *
     * @return void
     */
    public static function assertStackTraceMatches(StackTraceExpectations $expectations, array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        if ($expectations->allowToBePrefixOfActual) {
            TestCaseBase::assertGreaterThanOrEqual(count($expectations->frames), count($actual));
        } else {
            TestCaseBase::assertSame(count($expectations->frames), count($actual));
        }
        $expectedStackTraceCount = count($expectations->frames);
        $actualStackTraceCount = count($actual);
        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo($expectedStackTraceCount) as $i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            self::assertStackTraceFrameMatches($expectations->frames[$i], $actual[$i]);
        }
        $dbgCtx->popSubScope();
    }

    public static function assertStackTraceFrameMatches(StackTraceFrameExpectations $expectations, StackTraceFrame $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        TestCaseBase::assertSame($expectations->filename, $actual->filename);
        TestCaseBase::assertSame($expectations->function, $actual->function);
        if ($expectations->lineno->isValueSet()) {
            TestCaseBase::assertSame($expectations->lineno->getValue(), $actual->lineno);
        }
    }

    public function assertEquals(SpanToSendInterface $original): void
    {
        self::assertEqualOriginalAndDto($original, $this);
    }

    public function assertService(?string $targetType, ?string $targetName, string $destinationName, string $destinationResource, string $destinationType): void
    {
        TestCaseBase::assertNotNull($this->context);
        if ($targetType === null && $targetName === null) {
            TestCaseBase::assertNull($this->context->service);
        } else {
            TestCaseBase::assertNotNull($this->context->service);
            TestCaseBase::assertNotNull($this->context->service->target);
            TestCaseBase::assertSame($this->context->service->target->type, $targetType);
            TestCaseBase::assertSame($this->context->service->target->name, $targetName);
        }

        TestCaseBase::assertNotNull($this->context->destination);
        TestCaseBase::assertNotNull($this->context->destination->service);
        TestCaseBase::assertSame($this->context->destination->service->name, $destinationName);
        TestCaseBase::assertSame($this->context->destination->service->resource, $destinationResource);
        TestCaseBase::assertSame($this->context->destination->service->type, $destinationType);
    }

    public function getServiceTarget(): ?SpanContextServiceTargetDto
    {
        return ($this->context === null || $this->context->service === null) ? null : $this->context->service->target;
    }
}
