<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\CustomErrorData;
use Elastic\Apm\DistributedTracingData;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopDistributedTracingData
{
    use StaticClassTrait;

    /** @var DistributedTracingData */
    private static $data;

    /** @var string */
    private static $dataSerializedToString;

    public static function get(): DistributedTracingData
    {
        if (self::$data === null) {
            self::$data = new DistributedTracingData();
            self::$data->traceId = NoopExecutionSegment::TRACE_ID;
            self::$data->parentId = NoopExecutionSegment::ID;
            self::$data->isSampled = false;
        }

        return self::$data;
    }

    public static function serializedToString(): string
    {
        if (self::$dataSerializedToString === null) {
            self::$dataSerializedToString = self::get()->serializeToString();
        }

        return self::$dataSerializedToString;
    }
}
