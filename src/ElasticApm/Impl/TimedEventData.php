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
class TimedEventData extends TimestampedEventData
{
    use LoggableTrait;

    /** @var float */
    public $duration;

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValueIfNotNull('duration', $this->duration, /* ref */ $result);

        return $result;
    }
}
