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

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\CustomErrorData;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\TransactionBuilderInterface;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopTracer implements TracerInterface, LoggableInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function beginCurrentTransaction(
        ?string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(
        ?string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        return $callback(NoopTransaction::singletonInstance());
    }

    /** @inheritDoc */
    public function getCurrentTransaction(): TransactionInterface
    {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function getCurrentExecutionSegment(): ExecutionSegmentInterface
    {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function beginTransaction(
        ?string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function captureTransaction(
        ?string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        return $callback(NoopTransaction::singletonInstance());
    }

    /** @inheritDoc */
    public function newTransaction(string $name, string $type): TransactionBuilderInterface
    {
        return NoopTransactionBuilder::singletonInstance();
    }

    /** @inheritDoc */
    public function createErrorFromThrowable(Throwable $throwable): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function createCustomError(CustomErrorData $customErrorData): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function pauseRecording(): void
    {
    }

    /** @inheritDoc */
    public function resumeRecording(): void
    {
    }

    /** @inheritDoc */
    public function isRecording(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function setAgentEphemeralId(?string $ephemeralId): void
    {
    }

    /** @inheritDoc */
    public function getSerializedCurrentDistributedTracingData(): string
    {
        return NoopDistributedTracingData::serializedToString();
    }

    /** @inheritDoc */
    public function injectDistributedTracingHeaders(Closure $headerInjector): void
    {
    }
}
