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
abstract class ExecutionSegmentDataDeserializer extends DataDeserializer
{
    /** @var ExecutionSegmentData */
    private $result;

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
}
