<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\SpanData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentDataDeserializer extends DataDeserializer
{
    /** @var ExecutionSegmentData */
    private $result;

    abstract protected function executionSegmentContextData(): ExecutionSegmentContextData;

    protected function __construct(ExecutionSegmentData $result)
    {
        $this->result = $result;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function deserializeKeyValue(string $key, $value): bool
    {
        switch ($key) {
            case 'context':
                foreach ($value as $contextKey => $contextValue) {
                    if (!$this->deserializeContextKeyValue($contextKey, $contextValue)) {
                        throw ValidationUtil::buildException("Unknown key: context->`$contextKey'");
                    }
                }
                return true;

            case 'duration':
                $this->result->duration = ValidationUtil::assertValidDuration($value);
                return true;

            case 'id':
                $this->result->id = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'name':
                $this->result->name = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'timestamp':
                $this->result->timestamp = ValidationUtil::assertValidTimestamp($value);
                return true;

            case 'trace_id':
                $this->result->traceId = ValidationUtil::assertValidTraceId($value);
                return true;

            case 'type':
                $this->result->type = ValidationUtil::assertValidKeywordString($value);
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
        switch ($key) {
            case 'tags':
                $this->deserializeLabels($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param array<string, string|bool|int|float|null> $labels
     */
    protected function deserializeLabels(array $labels): void
    {
        ValidationUtil::assertValidLabels($labels);

        $this->executionSegmentContextData()->labels = $labels;
    }
}
