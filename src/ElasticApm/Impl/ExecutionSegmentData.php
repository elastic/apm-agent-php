<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ExecutionSegmentData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var string */
    public $id;

    /** @var string */
    public $traceId;

    /** @var float UTC based and in microseconds since Unix epoch */
    public $timestamp;

    /** @var float */
    public $duration;

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValue('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValue('type', $this->type, /* ref */ $result);
        SerializationUtil::addNameValue('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValue('trace_id', $this->traceId, /* ref */ $result);
        SerializationUtil::addNameValue(
            'timestamp',
            SerializationUtil::adaptTimestamp($this->timestamp),
            /* ref */ $result
        );
        SerializationUtil::addNameValue('duration', $this->duration, /* ref */ $result);

        return $result;
    }
}
