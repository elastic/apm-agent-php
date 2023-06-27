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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\Deserialization\JsonDeserializableInterface;
use ElasticApmTests\Util\Deserialization\JsonDeserializableTrait;
use ElasticApmTests\Util\Deserialization\JsonSerializableTrait;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TestCaseBase;
use JsonSerializable;

final class MockApmServerBehaviorDto implements JsonSerializable, JsonDeserializableInterface, LoggableInterface
{
    use JsonSerializableTrait;
    use JsonDeserializableTrait {
        deserializeFromString as deserializeFromStringImpl;
    }
    use LoggableTrait;

    /** @var callable(MockApmServer $mockApmServer, MixedMap $args): MockApmServerBehavior */
    public $buildCallable;

    /** @var array<string, mixed> */
    public $args;

    /**
     * @param callable(MockApmServer $mockApmServer, MixedMap $args): MockApmServerBehavior $buildCallable
     * @param array<string, mixed>                                                          $args
     *
     * @return self
     */
    public static function fromData(callable $buildCallable, array $args): self
    {
        $result = new self();
        $result->buildCallable = $buildCallable;
        $result->args = $args;
        $result->assertValid();
        return $result;
    }

    private function assertValid(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['this' => $this]);

        TestCaseBase::assertIsCallable($this->buildCallable);
        TestCaseBase::assertIsArray($this->buildCallable);
        /** @var array<mixed> $buildCallableAsArray */
        $buildCallableAsArray = $this->buildCallable;
        TestCaseBase::assertCount(2, $buildCallableAsArray);
        TestCaseBase::assertIsString($buildCallableAsArray[0]);
        TestCaseBase::assertTrue(class_exists($buildCallableAsArray[0]));
        TestCaseBase::assertTrue(method_exists($buildCallableAsArray[0], $buildCallableAsArray[1]));
    }

    public static function deserializeFromString(string $serializedToString): self
    {
        $result = new self();
        $result->deserializeFromStringImpl($serializedToString);
        $result->assertValid();
        return $result;
    }
}
