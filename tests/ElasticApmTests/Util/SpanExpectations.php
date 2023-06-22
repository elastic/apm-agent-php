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

final class SpanExpectations extends ExecutionSegmentExpectations
{
    /** @var Optional<string> */
    public $parentId;

    /** @var Optional<string> */
    public $transactionId;

    /** @var Optional<?string> */
    public $action;

    /** @var bool */
    public static $assumeSpanCompressionDisabled = false;

    /** @var Optional<?SpanCompositeExpectations> */
    public $composite;

    /** @var Optional<?SpanContextExpectations> */
    public $context;

    /** @var Optional<?string> */
    public $subtype;

    /** @var Optional<?StackTraceExpectations> */
    public $stackTrace = null;

    public static function setDefaults(): void
    {
        self::$assumeSpanCompressionDisabled = false;
    }

    public function __construct()
    {
        parent::__construct();
        $this->parentId = new Optional();
        $this->transactionId = new Optional();
        $this->action = new Optional();
        $this->composite = new Optional();
        if (self::$assumeSpanCompressionDisabled) {
            $this->composite->setValue(null);
        }
        $this->context = new Optional();
        $this->subtype = new Optional();
        $this->stackTrace = new Optional();
    }

    public function ensureNotNullContext(): SpanContextExpectations
    {
        if ($this->context->isValueSet()) {
            $value = $this->context->getValue();
            TestCaseBase::assertNotNull($value);
            return $value;
        }

        $value = new SpanContextExpectations();
        $this->context->setValue($value);
        return $value;
    }

    public function assumeNotNullContext(): SpanContextExpectations
    {
        TestCaseBase::assertNotNull($this->context->isValueSet());
        $value = $this->context->getValue();
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function setService(?string $targetType, ?string $targetName, string $destinationName, string $destinationResource, string $destinationType): void
    {
        $context = $this->ensureNotNullContext();

        $contextService = $context->ensureNotNullService();
        $contextService->target->type->setValue($targetType);
        $contextService->target->name->setValue($targetName);

        $contextDestination = $context->ensureNotNullDestination();
        $contextDestination->service->name->setValue($destinationName);
        $contextDestination->service->resource->setValue($destinationResource);
        $contextDestination->service->type->setValue($destinationType);
    }
}
