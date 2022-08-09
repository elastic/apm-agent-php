<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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
        string $srcCodeFile,
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
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logDebug(
        string $message,
        string $srcCodeFile,
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
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logWarning(
        string $message,
        string $srcCodeFile,
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
            ELASTIC_APM_LOG_LEVEL_WARNING,
            $message,
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logCritical(
        string $message,
        string $srcCodeFile,
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
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    public static function logCriticalThrowable(
        Throwable $throwable,
        string $message,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc
    ): void {
        self::logCritical(
            $message . '.'
            . ' ' . get_class($throwable) . ': ' . $throwable->getMessage()
            . PHP_EOL . 'Stack trace:' . PHP_EOL . $throwable->getTraceAsString(),
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc
        );
    }

    private static function logLevel(
        int $statementLevel,
        string $message,
        string $srcCodeFile,
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
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $message
        );
    }
}
