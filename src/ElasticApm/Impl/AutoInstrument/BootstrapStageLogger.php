<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BootstrapStageLogger
{
    /** @var int */
    private static $maxEnabledLevel;

    public static function configure(int $maxEnabledLevel): void
    {
        self::$maxEnabledLevel = $maxEnabledLevel;
    }

    public static function logTrace(
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::logLevel(
        /**
         * ELASTIC_APM_* constants are provided by the elastic_apm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTIC_APM_LOG_LEVEL_TRACE,
            $message,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logDebug(
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::logLevel(
        /**
         * ELASTIC_APM_* constants are provided by the elastic_apm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTIC_APM_LOG_LEVEL_DEBUG,
            $message,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logNotice(
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::logLevel(
        /**
         * ELASTIC_APM_* constants are provided by the elastic_apm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTIC_APM_LOG_LEVEL_NOTICE,
            $message,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logCritical(
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::logLevel(
        /**
         * ELASTIC_APM_* constants are provided by the elastic_apm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTIC_APM_LOG_LEVEL_CRITICAL,
            $message,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logCriticalThrowable(
        Throwable $throwable,
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        self::logCritical(
            $message . '.'
            . ' ' . get_class($throwable) . ': ' . $throwable->getMessage()
            . PHP_EOL . 'Stack trace:' . PHP_EOL . $throwable->getTraceAsString(),
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    private static function logLevel(
        int $statementLevel,
        string $message,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        if (self::$maxEnabledLevel < $statementLevel) {
            return;
        }

        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        \elastic_apm_log(
            0 /* $isForced */,
            $statementLevel,
            'Bootstrap' /* category */,
            __FILE__,
            $srcCodeLine,
            $srcCodeFunc,
            $message
        );
    }
}
