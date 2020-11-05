<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait ExecutionSegmentDataDeserializerTrait
{
    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function executionSegmentDeserializeKeyValue(string $key, $value): bool
    {
        return (new class extends ExecutionSegmentData {
            /**
             * @param string               $key
             * @param mixed                $value
             * @param ExecutionSegmentData $result
             * @param mixed                $derivedDeserializer
             *
             * @return bool
             */
            public static function deserializeKeyValueImpl(
                string $key,
                $value,
                ExecutionSegmentData $result,
                $derivedDeserializer
            ): bool {
                switch ($key) {
                    case 'context':
                        foreach ($value as $contextKey => $contextValue) {
                            if (!$derivedDeserializer->deserializeContextKeyValue($contextKey, $contextValue)) {
                                throw ValidationUtil::buildException("Unknown key: context->`$contextKey'");
                            }
                        }
                        return true;

                    case 'duration':
                        $result->duration = ValidationUtil::assertValidDuration($value);
                        return true;

                    case 'id':
                        $result->id = ValidationUtil::assertValidExecutionSegmentId($value);
                        return true;

                    case 'name':
                        $result->name = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    case 'timestamp':
                        $result->timestamp = ValidationUtil::assertValidTimestamp($value);
                        return true;

                    case 'trace_id':
                        $result->traceId = ValidationUtil::assertValidTraceId($value);
                        return true;

                    case 'type':
                        $result->type = ValidationUtil::assertValidKeywordString($value);
                        return true;

                    default:
                        return false;
                }
            }
        })->deserializeKeyValueImpl($key, $value, $this->result, $this);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function executionSegmentDeserializeContextKeyValue(string $key, $value): bool
    {
        switch ($key) {
            case 'tags':
                $this->deserializeLabels($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function deserializeContextKeyValue(string $key, $value): bool
    {
        return $this->executionSegmentDeserializeContextKeyValue($key, $value);
    }

    /**
     * @param array<string, string|bool|int|float|null> $labels
     */
    protected function deserializeLabels(array $labels): void
    {
        ValidationUtil::assertValidLabels($labels);

        (new class extends ExecutionSegmentData {
            /**
             * @param ExecutionSegmentData                      $result
             * @param array<string, string|bool|int|float|null> $labels
             */
            public static function setLabels(ExecutionSegmentData $result, array $labels): void
            {
                $result->labels = $labels;
            }
        })->setLabels($this->result, $labels);
    }
}
