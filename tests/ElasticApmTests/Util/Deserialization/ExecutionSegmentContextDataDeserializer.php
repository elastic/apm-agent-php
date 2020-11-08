<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentContextDataDeserializer extends DataDeserializer
{
    /** @var ExecutionSegmentContextData */
    private $result;

    protected function __construct(ExecutionSegmentContextData $result)
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
            case 'tags':
                $this->result->labels = ValidationUtil::assertValidLabels($value);
                return true;

            default:
                return false;
        }
    }
}
