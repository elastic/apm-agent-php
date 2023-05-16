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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\ComponentTests\Util\OsUtilForTests;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OutputDebugString
{
    use StaticClassTrait;

    /** @var ?bool */
    private static $isEnabled = null;

    /**
     * @noinspection RedundantSuppression, PhpFullyQualifiedNameUsageInspection, PhpUndefinedClassInspection
     * @phpstan-ignore-next-line
     */
    /** @var \FFI
     */
    /**
     * @phpstan-ignore-next-line
     */
    private static $ffi;

    public static function isEnabled(): bool
    {
        if (self::$isEnabled === null) {
            self::$isEnabled = self::calcIsEnabled();
        }

        return self::$isEnabled;
    }

    private static function calcIsEnabled(): bool
    {
        // FFI was introduced in PHP 7.4
        if (!OsUtilForTests::isWindows() || (PHP_VERSION_ID < 70400)) {
            return false;
        }

        if (!isset(self::$ffi)) {
            try {
                /**
                 * @noinspection RedundantSuppression, PhpFullyQualifiedNameUsageInspection, PhpUndefinedClassInspection
                 * @phpstan-ignore-next-line
                 */
                self::$ffi = \FFI::cdef('void OutputDebugStringA( const char* test );', 'Kernel32.dll');
            } catch (Throwable $throwable) {
                return false;
            }
        }

        return true;
    }

    public static function write(string $text): void
    {
        /**
         * @noinspection RedundantSuppression, PhpUndefinedMethodInspection
         * @phpstan-ignore-next-line
         */
        self::$ffi->OutputDebugStringA($text);
    }
}
