<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Metadata;
use Elastic\Apm\NameVersionData;
use Elastic\Apm\ProcessData;
use Elastic\Apm\ServiceData;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Deserializer
{
    use StaticClassTrait;

    /**
     * @param array<string, mixed> $decodedData
     * @param NameVersionData      $result
     */
    private static function deserializeNameVersionData(array $decodedData, NameVersionData $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'name':
                        $result->setName(ValidationUtil::assertValidKeywordString($value));
                        return;

                    case 'version':
                        $result->setVersion(ValidationUtil::assertValidKeywordString($value));
                        return;

                    default:
                        throw self::buildUnknownKeyException($key, $value);
                }
            }
        );

        ValidationUtil::assertValidNameVersionData($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param ProcessData          $result
     */
    private static function deserializeProcessData(array $decodedData, ProcessData $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'pid':
                        $result->setPid(ValidationUtil::assertValidProcessId($value));
                        return;

                    default:
                        throw self::buildUnknownKeyException($key, $value);
                }
            }
        );

        ValidationUtil::assertValidProcessData($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param ServiceData          $result
     */
    private static function deserializeServiceData(array $decodedData, ServiceData $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'name':
                        $result->setName(ValidationUtil::assertValidServiceName($value));
                        return;

                    case 'version':
                        $result->setVersion(ValidationUtil::assertValidKeywordString($value));
                        return;

                    case 'environment':
                        $result->setEnvironment(ValidationUtil::assertValidKeywordString($value));
                        return;

                    case 'agent':
                        self::deserializeNameVersionData($value, $result->agent());
                        return;

                    case 'framework':
                        self::deserializeNameVersionData($value, $result->framework());
                        return;

                    case 'language':
                        self::deserializeNameVersionData($value, $result->language());
                        return;

                    case 'runtime':
                        self::deserializeNameVersionData($value, $result->runtime());
                        return;

                    default:
                        throw self::buildUnknownKeyException($key, $value);
                }
            }
        );

        ValidationUtil::assertValidServiceData($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param Metadata             $result
     */
    public static function deserializeMetadata(array $decodedData, Metadata $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'process':
                        self::deserializeProcessData($value, $result->process());
                        return;

                    case 'service':
                        self::deserializeServiceData($value, $result->service());
                        return;

                    default:
                        throw self::buildUnknownKeyException($key, $value);
                }
            }
        );

        ValidationUtil::assertValidMetadata($result);
    }

    /**
     * @param string                         $key
     * @param mixed                          $value
     * @param ExecutionSegmentContextTestDto $result
     */
    private static function deserializeExecutionSegmentContextKeyValue(
        string $key,
        $value,
        ExecutionSegmentContextTestDto $result
    ): void {
        switch ($key) {
            case 'tags':
                self::deserializeLabels($value, $result);
                return;

            default:
                throw self::buildUnknownKeyException($key, $value);
        }
    }

    /**
     * @param array<string, string|bool|int|float|null> $labels
     * @param ExecutionSegmentContextTestDto            $result
     */
    private static function deserializeLabels(array $labels, ExecutionSegmentContextTestDto $result): void
    {
        ValidationUtil::assertValidLabels($labels);

        foreach ($labels as $key => $value) {
            $result->setLabel($key, $value);
        }
    }

    /**
     * @param string                  $key
     * @param mixed                   $value
     * @param ExecutionSegmentTestDto $result
     */
    private static function deserializeExecutionSegmentKeyValue(
        string $key,
        $value,
        ExecutionSegmentTestDto $result
    ): void {
        switch ($key) {
            case 'duration':
                $result->setDuration(ValidationUtil::assertValidDuration($value));
                return;

            case 'id':
                $result->setId(ValidationUtil::assertValidExecutionSegmentId($value));
                return;

            case 'name':
                $result->setName(ValidationUtil::assertValidKeywordString($value));
                return;

            case 'timestamp':
                $result->setTimestamp(ValidationUtil::assertValidTimestamp($value));
                return;

            case 'trace_id':
                $result->setTraceId(ValidationUtil::assertValidTraceId($value));
                return;

            case 'type':
                $result->setType(ValidationUtil::assertValidKeywordString($value));
                return;

            default:
                throw self::buildUnknownKeyException($key, $value);
        }
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param SpanContextTestDto   $result
     */
    private static function deserializeSpanContext(array $decodedData, SpanContextTestDto $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    default:
                        self::deserializeExecutionSegmentContextKeyValue($key, $value, $result);
                }
            }
        );

        ValidationUtil::assertValidSpanContext($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param SpanTestDto          $result
     */
    public static function deserializeSpan(array $decodedData, SpanTestDto $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'action':
                        $result->setAction(ValidationUtil::assertValidKeywordString($value));
                        return;

                    case 'context':
                        self::deserializeSpanContext($value, $result->contextDto());
                        return;

                    case 'parent_id':
                        $result->setParentId(ValidationUtil::assertValidExecutionSegmentId($value));
                        return;

                    case 'start':
                        $result->setStart(ValidationUtil::assertValidSpanStart($value));
                        return;

                    case 'subtype':
                        $result->setSubtype(ValidationUtil::assertValidKeywordString($value));
                        return;

                    case 'transaction_id':
                        $result->setTransactionId(ValidationUtil::assertValidExecutionSegmentId($value));
                        return;

                    default:
                        self::deserializeExecutionSegmentKeyValue($key, $value, $result);
                }
            }
        );

        ValidationUtil::assertValidSpan($result);
    }

    /**
     * @param array<string, mixed>      $decodedData
     * @param TransactionContextTestDto $result
     */
    private static function deserializeTransactionContext(
        array $decodedData,
        TransactionContextTestDto $result
    ): void {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    default:
                        self::deserializeExecutionSegmentContextKeyValue($key, $value, $result);
                }
            }
        );

        ValidationUtil::assertValidTransactionContext($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param TransactionTestDto   $result
     */
    public static function deserializeTransaction(array $decodedData, TransactionTestDto $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'context':
                        self::deserializeTransactionContext($value, $result->contextDto());
                        return;

                    case 'parent_id':
                        $result->setParentId(ValidationUtil::assertValidExecutionSegmentId($value));
                        return;

                    case 'span_count':
                        self::deserializeSpanCount($value, $result);
                        return;

                    default:
                        self::deserializeExecutionSegmentKeyValue($key, $value, $result);
                }
            }
        );

        ValidationUtil::assertValidTransaction($result);
    }

    /**
     * @param array<string, mixed> $decodedData
     * @param TransactionTestDto   $result
     */
    private static function deserializeSpanCount(array $decodedData, TransactionTestDto $result): void
    {
        self::processKeyValuePairs(
            $decodedData,
            /**
             * @param string $key
             * @param mixed  $value
             */
            function (string $key, $value) use ($result): void {
                switch ($key) {
                    case 'dropped':
                        $result->setDroppedSpansCount(ValidationUtil::assertValidTransactionDroppedSpansCount($value));
                        return;

                    case 'started':
                        $result->setStartedSpansCount(ValidationUtil::assertValidTransactionStartedSpansCount($value));
                        return;

                    default:
                        throw self::buildUnknownKeyException("span_count->`$key'", $value);
                }
            }
        );
    }

    /**
     *
     * @param array<string, mixed> $decodedData
     * @param callable             $processKeyValue
     *
     * @phpstan-param callable(string, mixed): void $processKeyValue
     */
    private static function processKeyValuePairs(array $decodedData, callable $processKeyValue): void
    {
        foreach ($decodedData as $key => $value) {
            $processKeyValue($key, $value);
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return RuntimeException
     */
    private static function buildUnknownKeyException(string $key, $value): RuntimeException
    {
        return new RuntimeException(
            ExceptionUtil::buildMessageWithStacktrace(
                // "Unknown key: `$key'. Value: " . DbgUtil::formatValue($value),
                "Unknown key: `$key'. Value: " . strval($value),
                /* numberOfStackFramesToSkip */ 1
            )
        );
    }
}
