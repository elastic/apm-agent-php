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
class TimestampedEventData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var float UTC based and in microseconds since Unix epoch */
    public $timestamp;

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull(
            'timestamp',
            self::adaptTimestamp($this->timestamp),
            /* ref */ $result
        );

        return $result;
    }

    /**
     * @param float $timestamp
     *
     * @return float|int
     */
    protected static function adaptTimestamp(float $timestamp)
    {
        // If int type is large enough to hold 64-bit (8 bytes) use it instead of float
        return (PHP_INT_SIZE >= 8) ? intval($timestamp) : $timestamp;
    }
}
