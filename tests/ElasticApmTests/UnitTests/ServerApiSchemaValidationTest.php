<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Closure;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\Tests\Util\SerializationTestUtil;
use Elastic\Apm\Tests\Util\ServerApiSchemaValidationException;
use Elastic\Apm\Tests\Util\ServerApiSchemaValidator;

class ServerApiSchemaValidationTest extends UnitTestCaseBase
{
    private function createSerializedTransaction(): string
    {
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->context()->setLabel('test_label_key', 'test_label_value');
        $tx->end();
        return SerializationUtil::serializeAsJson($tx);
    }

    private function createSerializedSpan(): string
    {
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->context()->setLabel('test_label_key', 'test_label_value');
        $span->end();
        $tx->end();
        return SerializationUtil::serializeAsJson($span);
    }

    /**
     * @param Closure(string, Closure(string): void): void $testImpl
     * @param string $serializedEvent
     * @param Closure(string): void $validateSerializedEvent
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
     * @param array<mixed>         $pathToElement
     *
     * @return mixed
     */
    private function &findJsonElement(array &$deserializedJson, array $pathToElement)
    {
        $currentJsonElement = &$deserializedJson;
        foreach ($pathToElement as $currentPathPart) {
            $this->assertArrayHasKey($currentPathPart, $currentJsonElement);
            $currentJsonElement = &$currentJsonElement[$currentPathPart];
        }
        return $currentJsonElement;
    }

    /**
     * @param string       $serializedEvent
     * @param Closure(string): void $validateSerializedEvent
     * @param array<mixed> $pathToParentElement
     */
    private function unknownPropertyTestImpl(
        string $serializedEvent,
        Closure $validateSerializedEvent,
        array $pathToParentElement
    ): void {
        $unknownPropertyName = 'dummy_property_added_to_corrupt_key';
        $unknownPropertyValue = 'dummy_property_added_to_corrupt_value';
        $deserializedEventToCorrupt = SerializationTestUtil::deserializeJson($serializedEvent, /* asAssocArray */ true);

        $parentJsonNode = &$this->findJsonElement(/* ref */ $deserializedEventToCorrupt, $pathToParentElement);
        $this->assertArrayNotHasKey($unknownPropertyName, $parentJsonNode);
        $parentJsonNode[$unknownPropertyName] = $unknownPropertyValue;

        $this->assertThrows(
            ServerApiSchemaValidationException::class,
            function () use ($validateSerializedEvent, $deserializedEventToCorrupt, $serializedEvent) {
                $validateSerializedEvent(
                    SerializationUtil::serializeAsJson(
                        $deserializedEventToCorrupt,
                        "Corrupted event based on: $serializedEvent"
                    )
                );
            },
            function (ServerApiSchemaValidationException $ex) use ($unknownPropertyName, $unknownPropertyValue) {
                $this->assertStringContainsString($unknownPropertyName, $ex->getMessage());
                $this->assertStringContainsString($unknownPropertyValue, $ex->getMessage());
            }
        );
    }

    /**
     * @param array<mixed> $pathToParentElement
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
     * @param array<mixed> $pathToElement
     * @param mixed        $wrongTypeValue
     */
    private function wrongPropertyValueTypeTestImpl(
        string $serializedEvent,
        Closure $validateSerializedEvent,
        array $pathToElement,
        $wrongTypeValue
    ): void {
        $deserializedEventToCorrupt = SerializationTestUtil::deserializeJson($serializedEvent, /* asAssocArray */ true);
        $propToCorrupt = &$this->findJsonElement(/* ref */ $deserializedEventToCorrupt, $pathToElement);
        $propToCorrupt = $wrongTypeValue;

        $this->assertThrows(
            ServerApiSchemaValidationException::class,
            function () use ($validateSerializedEvent, $deserializedEventToCorrupt, $serializedEvent) {
                $validateSerializedEvent(
                    SerializationUtil::serializeAsJson(
                        $deserializedEventToCorrupt,
                        "Corrupted event based on: $serializedEvent"
                    )
                );
            },
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
     * @param array<mixed> $pathToElement
     * @param mixed        $wrongTypeValue
     *
     * @return Closure(string, Closure(string): void): void
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
