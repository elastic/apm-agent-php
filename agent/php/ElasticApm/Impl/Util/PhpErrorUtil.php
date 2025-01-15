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

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PhpErrorUtil
{
    use StaticClassTrait;

    /**
     * @return array<int, string>
     */
    private static function flagsValueToNameMap(): array
    {
        /** @var ?array<int, string> $flags */
        static $flags = null;

        if ($flags === null) {
            $flags = [];
            $addToFlagsIfDefined = function (string $flagName, ?int $flagValue = null) use (&$flags): void {
                if (defined($flagName)) {
                    $flagValueToUse = $flagValue ?? constant($flagName);
                    if (is_int($flagValueToUse)) {
                        $flags[$flagValueToUse] = $flagName;
                    }
                }
            };

            $addToFlagsIfDefined('E_ERROR');
            $addToFlagsIfDefined('E_RECOVERABLE_ERROR');
            $addToFlagsIfDefined('E_WARNING');
            $addToFlagsIfDefined('E_PARSE');
            $addToFlagsIfDefined('E_NOTICE');
            // PHP 8.4: E_STRICT constant deprecated
            if (PHP_VERSION_ID < 80400) {
                $addToFlagsIfDefined('E_STRICT');
            } else {
                $addToFlagsIfDefined('E_STRICT', /* E_STRICT: */ 2048);
            }
            $addToFlagsIfDefined('E_DEPRECATED');
            $addToFlagsIfDefined('E_CORE_ERROR');
            $addToFlagsIfDefined('E_CORE_WARNING');
            $addToFlagsIfDefined('E_COMPILE_ERROR');
            $addToFlagsIfDefined('E_COMPILE_WARNING');
            $addToFlagsIfDefined('E_USER_ERROR');
            $addToFlagsIfDefined('E_USER_WARNING');
            $addToFlagsIfDefined('E_USER_NOTICE');
            $addToFlagsIfDefined('E_USER_DEPRECATED');
        }

        return $flags;
    }

    public static function convertErrorReportingValueToHumanReadableString(int $errorReporting): string
    {
        $flags = self::flagsValueToNameMap();
        $result = '';
        $appendToResult = function (string $separator, string $textToAppend) use (&$result): void {
            if (!TextUtil::isEmptyString($result)) {
                $result .= $separator;
            }
            $result .= $textToAppend;
        };

        $remaingValue = $errorReporting;
        foreach ($flags as $flagValue => $flagName) {
            $partToAddToResult = (($errorReporting & $flagValue) === 0 ? '~' : '') . $flagName;
            $appendToResult(' & ', $partToAddToResult);
            $remaingValue &= ~$flagValue;
        }

        if ($remaingValue !== 0) {
            $appendToResult(' ', '[remaining value: ' . $remaingValue . ']');
        }

        $appendToResult(' ', '[value as int: ' . $errorReporting . ']');

        return $result;
    }

    public static function getTypeName(int $type): ?string
    {
        return ArrayUtil::getValueIfKeyExistsElse($type, self::flagsValueToNameMap(), null);
    }
}
