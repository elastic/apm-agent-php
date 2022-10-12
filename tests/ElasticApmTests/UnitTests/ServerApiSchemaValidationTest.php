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

namespace ElasticApmTests\UnitTests;

use Closure;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\UnitTests\Util\MockEventSink;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\Deserialization\ServerApiSchemaValidationException;
use ElasticApmTests\Util\Deserialization\ServerApiSchemaValidator;
use ElasticApmTests\Util\TestCaseBase;

class ServerApiSchemaValidationTest extends TestCaseBase
{
    private function createSerializedTransaction(): string
    {
        $mockEventSink = new MockEventSink();
        $tracer = self::buildTracerForTests($mockEventSink)->build();
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->context()->setLabel('test_label_key', 'test_label_value');
        $tx->end();
        return SerializationUtil::serializeAsJson($tx);
    }

    private function createSerializedSpan(): string
    {
        $mockEventSink = new MockEventSink();
        $tracer = self::buildTracerForTests($mockEventSink)->build();
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->context()->setLabel('test_label_key', 'test_label_value');
        $span->end();
        $tx->end();
        return SerializationUtil::serializeAsJson($span);
    }

    /**
     * @param Closure(string, Closure(string): void): void $testImpl
     * @param string                                       $serializedEvent
     * @param Closure(string): void                        $validateSerializedEvent
     */
    public function callTestOnOneCreateValidateCombination(
        Closure $testImpl,
        string $serializedEvent,
        Closure $validateSerializedEvent
    ): void {
        // Make sure serialized data passes validation before we corrupt it
        $validateSerializedEvent($serializedEvent);

        $testImpl($serializedEvent, $validateSerializedEvent);
    }

    /**
     * @param Closure(string, Closure(string): void): void $testImpl
     */
    public function callTestOnTransactionCombination(Closure $testImpl): void
    {
        $this->callTestOnOneCreateValidateCombination(
            $testImpl,
            self::createSerializedTransaction(),
            function (string $serializedTransaction): void {
                ServerApiSchemaValidator::validateTransaction($serializedTransaction);
            }
        );
    }

    /**
     * @param Closure(string, Closure(string): void): void $testImpl
     */
    public function callTestOnSpanCombination(Closure $testImpl): void
    {
        $this->callTestOnOneCreateValidateCombination(
            $testImpl,
            self::createSerializedSpan(),
            function (string $serializedSpan): void {
                ServerApiSchemaValidator::validateSpan($serializedSpan);
            }
        );
    }

    /**
     * @param Closure(string, Closure(string): void): void $testImpl
     */
    public function callTestOnAllCreateValidateCombinations(Closure $testImpl): void
    {
        $this->callTestOnTransactionCombination($testImpl);
        $this->callTestOnSpanCombination($testImpl);
    }

    /**
     * @param array<string, mixed> $deserializedJson
     * @param array<string>        $pathToElement
     *
     * @return mixed
     */
    private function &findJsonElement(array &$deserializedJson, array $pathToElement)
    {
        $currentJsonElement = &$deserializedJson;
        foreach ($pathToElement as $currentPathPart) {
            self::assertIsArray($currentJsonElement);
            self::assertArrayHasKey($currentPathPart, $currentJsonElement);
            $currentJsonElement = &$currentJsonElement[$currentPathPart];
        }
        return $currentJsonElement;
    }

    /**
     * @param string       $serializedEvent
     * @param Closure(string): void $validateSerializedEvent
     * @param array<string> $pathToParentElement
     */
    private function unknownPropertyTestImpl(
        string $serializedEvent,
        Closure $validateSerializedEvent,
        array $pathToParentElement
    ): void {
        $unknownPropertyName = 'dummy_property_added_to_corrupt_key';
        $unknownPropertyValue = 'dummy_property_added_to_corrupt_value';
        $deserializedEventToCorrupt = JsonUtil::decode($serializedEvent, /* asAssocArray */ true);

        /** @var array<mixed, mixed> $parentJsonNode */
        $parentJsonNode = &$this->findJsonElement(/* ref */ $deserializedEventToCorrupt, $pathToParentElement);
        ArrayUtilForTests::addUnique($unknownPropertyName, $unknownPropertyValue, /* ref */ $parentJsonNode);

        $this->assertThrows(
            ServerApiSchemaValidationException::class,
            function () use ($validateSerializedEvent, $deserializedEventToCorrupt) {
                $validateSerializedEvent(SerializationUtil::serializeAsJson($deserializedEventToCorrupt));
            },
            '' /* message */,
            function (ServerApiSchemaValidationException $ex) use ($unknownPropertyName, $unknownPropertyValue) {
                $this->assertStringContainsString($unknownPropertyName, $ex->getMessage());
                $this->assertStringContainsString($unknownPropertyValue, $ex->getMessage());
            }
        );
    }

    /**
     * @param array<string> $pathToParentElement
     *
     * @return Closure(string, Closure(string): void): void
     */
    private function createUnknownPropertyTestImpl(array $pathToParentElement): Closure
    {
        return function (string $serializedEvent, Closure $validateSerializedEvent) use ($pathToParentElement): void {
            $this->unknownPropertyTestImpl($serializedEvent, $validateSerializedEvent, $pathToParentElement);
        };
    }

    public function testUnknownPropertyTopLevel(): void
    {
        $this->callTestOnAllCreateValidateCombinations(
            $this->createUnknownPropertyTestImpl(/* pathToParentElement: */ [])
        );
    }

    public function testUnknownPropertyInsideContext(): void
    {
        $this->callTestOnAllCreateValidateCombinations(
            $this->createUnknownPropertyTestImpl(/* pathToParentElement: */ ['context'])
        );
    }

    public function testUnknownPropertyTransactionInsideSpanCount(): void
    {
        $this->callTestOnTransactionCombination(
            $this->createUnknownPropertyTestImpl(/* pathToParentElement: */ ['span_count'])
        );
    }

    /**
     * @param string       $serializedEvent
     * @param Closure(string): void $validateSerializedEvent
     * @param array<string> $pathToElement
     * @param mixed        $wrongTypeValue
     */
    private function wrongPropertyValueTypeTestImpl(
        string $serializedEvent,
        Closure $validateSerializedEvent,
        array $pathToElement,
        $wrongTypeValue
    ): void {
        $deserializedEventToCorrupt = JsonUtil::decode($serializedEvent, /* asAssocArray */ true);
        $propToCorrupt = &$this->findJsonElement(/* ref */ $deserializedEventToCorrupt, $pathToElement);
        $propToCorrupt = $wrongTypeValue;

        $this->assertThrows(
            ServerApiSchemaValidationException::class,
            function () use ($validateSerializedEvent, $deserializedEventToCorrupt) {
                $validateSerializedEvent(SerializationUtil::serializeAsJson($deserializedEventToCorrupt));
            },
            '' /* message */,
            function (ServerApiSchemaValidationException $ex) use ($pathToElement) {
                foreach ($pathToElement as $pathPart) {
                    if (is_string($pathPart)) {
                        $this->assertStringContainsString($pathPart, $ex->getMessage());
                    }
                }
            }
        );
    }

    /**
     * @param array<string> $pathToElement
     * @param mixed         $wrongTypeValue
     *
     * @return Closure(string, Closure(string): void): void
     * @noinspection PhpSameParameterValueInspection
     */
    private function createWrongPropertyValueTypeTestImpl(array $pathToElement, $wrongTypeValue): Closure
    {
        return function (
            string $serializedEvent,
            Closure $validateSerializedEvent
        ) use (
            $pathToElement,
            $wrongTypeValue
        ): void {
            $this->wrongPropertyValueTypeTestImpl(
                $serializedEvent,
                $validateSerializedEvent,
                $pathToElement,
                $wrongTypeValue
            );
        };
    }

    public function testStringTimestamp(): void
    {
        $this->callTestOnAllCreateValidateCombinations(
            $this->createWrongPropertyValueTypeTestImpl(/* pathToElement: */ ['timestamp'], '123456')
        );
    }
}
