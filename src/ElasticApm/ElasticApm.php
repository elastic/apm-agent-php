<?php

declare(strict_types=1);

namespace ElasticApm;

final class ElasticApm
{
    /**
     * Constructor is hidden because it's a "static" class
     */
    private function __construct()
    {
    }

    public static function beginCurrentTransaction(?string $name, string $type): TransactionInterface
    {
        return TracerSingleton::get()->beginCurrentTransaction($name, $type);
    }

    public static function getCurrentTransaction(): TransactionInterface
    {
        return TracerSingleton::get()->getCurrentTransaction();
    }

    public static function endCurrentTransaction(): void
    {
        self::getCurrentTransaction()->end();
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

    public static function endCurrentSpan(): void
    {
        self::getCurrentSpan()->end();
    }
}
