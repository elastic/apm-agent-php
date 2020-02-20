<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Impl\TracerSingleton;
use ElasticApm\Impl\Util\StaticClassTrait;

final class ElasticApm
{
    use StaticClassTrait;

    public const VERSION = '0.1-preview';

    public static function beginCurrentTransaction(?string $name, string $type): TransactionInterface
    {
        return TracerSingleton::get()->beginCurrentTransaction($name, $type);
    }

    public static function getCurrentTransaction(): TransactionInterface
    {
        return TracerSingleton::get()->getCurrentTransaction();
    }

    public static function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return self::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);
    }

    public static function getCurrentSpan(): SpanInterface
    {
        return self::getCurrentTransaction()->getCurrentSpan();
    }
}
