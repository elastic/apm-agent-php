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

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class StackTraceFrameBase
{
    /** @var ?string */
    public $file = null;

    /** @var ?int */
    public $line = null;

    /** @var ?string */
    public $class = null;

    /** @var ?string */
    public $function = null;

    /** @var ?bool */
    public $isStaticMethod = null;

    /** @var ?object */
    public $thisObj = null;

    /** @var null|mixed[] */
    public $args = null;

    /**
     * @param array<string, mixed> $debugBacktraceFormatFrame
     */
    public function copyDataFromFromDebugBacktraceFrame(
        array $debugBacktraceFormatFrame,
        ?LoggerFactory $loggerFactory = null
    ): void {
        /** @var ?Logger $logger */
        $logger = null;
        if ($loggerFactory !== null && $loggerFactory->isEnabledForLevel(LogLevel::ERROR)) {
            $logger = $loggerFactory->loggerForClass(LogCategory::INFRASTRUCTURE, __NAMESPACE__, __CLASS__, __FILE__)
                                    ->addContext('debugBacktraceFormatFrame', $debugBacktraceFormatFrame);
        }

        $this->file = self::getNullableStringValue(StackTraceUtil::FILE_KEY, $debugBacktraceFormatFrame, $logger);
        $this->line = self::getNullableIntValue(StackTraceUtil::LINE_KEY, $debugBacktraceFormatFrame, $logger);
        $this->class
            = self::getNullableStringValue(StackTraceUtil::CLASS_KEY, $debugBacktraceFormatFrame, $logger);
        $this->function
            = self::getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $debugBacktraceFormatFrame, $logger);

        $this->thisObj
            = self::getNullableObjectValue(StackTraceUtil::THIS_OBJECT_KEY, $debugBacktraceFormatFrame, $logger);
        $this->args = self::getNullableArrayValue(StackTraceUtil::ARGS_KEY, $debugBacktraceFormatFrame, $logger);

        $type = self::getNullableStringValue(StackTraceUtil::TYPE_KEY, $debugBacktraceFormatFrame, $logger);
        if ($type !== null) {
            switch ($type) {
                case StackTraceUtil::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE:
                    $this->isStaticMethod = true;
                    break;
                case StackTraceUtil::FUNCTION_IS_METHOD_TYPE_VALUE:
                    $this->isStaticMethod = false;
                    break;
                default:
                    $logger && ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Unexpected `' . StackTraceUtil::TYPE_KEY . '\' value', ['type' => $type]);
                    break;
            }
        }
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     * @param ?Logger              $logger
     *
     * @return ?string
     */
    private static function getNullableStringValue(
        string $key,
        array $debugBacktraceFormatFrame,
        ?Logger $logger
    ): ?string {
        /** @var ?string $value */
        $value = self::getNullableValue($key, 'is_string', 'string', $debugBacktraceFormatFrame, $logger);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     * @param ?Logger              $logger
     *
     * @return ?int
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getNullableIntValue(
        string $key,
        array $debugBacktraceFormatFrame,
        ?Logger $logger
    ): ?int {
        /** @var ?int $value */
        $value = self::getNullableValue($key, 'is_int', 'int', $debugBacktraceFormatFrame, $logger);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     * @param ?Logger              $logger
     *
     * @return ?object
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getNullableObjectValue(
        string $key,
        array $debugBacktraceFormatFrame,
        ?Logger $logger
    ): ?object {
        /** @var ?object $value */
        $value = self::getNullableValue($key, 'is_object', 'object', $debugBacktraceFormatFrame, $logger);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     * @param ?Logger              $logger
     *
     * @return null|mixed[]
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getNullableArrayValue(
        string $key,
        array $debugBacktraceFormatFrame,
        ?Logger $logger
    ): ?array {
        /** @var ?array<mixed> $value */
        $value = self::getNullableValue($key, 'is_array', 'array', $debugBacktraceFormatFrame, $logger);
        return $value;
    }

    /**
     * @param string                $key
     * @param callable(mixed): bool $isValueTypeFunc
     * @param string                $dbgExpectedType
     * @param array<string, mixed>  $debugBacktraceFormatFrame
     * @param ?Logger               $logger
     *
     * @return mixed
     */
    private static function getNullableValue(
        string $key,
        callable $isValueTypeFunc,
        string $dbgExpectedType,
        array $debugBacktraceFormatFrame,
        ?Logger $logger
    ) {
        if (!array_key_exists($key, $debugBacktraceFormatFrame)) {
            return null;
        }

        $value = $debugBacktraceFormatFrame[$key];
        if ($value === null) {
            return null;
        }

        if (!$isValueTypeFunc($value)) {
            $logger && ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Unexpected type for value under key (expected ' . $dbgExpectedType . ')',
                ['$key' => $key, 'value type' => DbgUtil::getType($value), 'value' => $value]
            );
            return null;
        }

        return $value;
    }

    public static function isLocationProperty(string $propName): bool
    {
        return $propName === 'file' || $propName === 'line';
    }

    /** @noinspection PhpUnused */
    public function copyLocationPropertiesFrom(StackTraceFrameBase $src): void
    {
        foreach (get_object_vars($src) as $propName => $srcPropVal) {
            if (self::isLocationProperty($propName)) {
                $this->{$propName} = $srcPropVal;
            }
        }
    }

    /** @noinspection PhpUnused */
    public function copyNonLocationPropertiesFrom(StackTraceFrameBase $src): void
    {
        foreach (get_object_vars($src) as $propName => $srcPropVal) {
            if (!self::isLocationProperty($propName)) {
                $this->{$propName} = $srcPropVal;
            }
        }
    }

    public function resetNonLocationProperties(): void
    {
        foreach (get_object_vars($this) as $propName => $srcPropVal) {
            if (!self::isLocationProperty($propName)) {
                $this->{$propName} = null;
            }
        }
    }
}
