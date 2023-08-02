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

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\StackTraceFrame;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class StackTraceUtil
{
    public const FILE_KEY = 'file';
    public const LINE_KEY = 'line';
    public const FUNCTION_KEY = 'function';
    public const CLASS_KEY = 'class';
    public const TYPE_KEY = 'type';
    public const FUNCTION_IS_STATIC_METHOD_TYPE_VALUE = '::';
    public const FUNCTION_IS_METHOD_TYPE_VALUE = '->';
    public const THIS_OBJECT_KEY = 'object';
    public const ARGS_KEY = 'args';

    public const FILE_NAME_NOT_AVAILABLE_SUBSTITUTE = 'FILE NAME N/A';
    public const LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE = 0;

    private const ELASTIC_APM_FQ_NAME_PREFIX = 'Elastic\\Apm\\';
    private const ELASTIC_APM_INTERNAL_FUNCTION_NAME_PREFIX = 'elastic_apm_';


    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var Logger */
    private $logger;

    /** @var string */
    private $namePrefixForFramesToHide;

    /** @var string */
    private $namePrefixForInternalFramesToHide;

    public function __construct(LoggerFactory $loggerFactory, string $namePrefixForFramesToHide = self::ELASTIC_APM_FQ_NAME_PREFIX, string $namePrefixForInternalFramesToHide = self::ELASTIC_APM_INTERNAL_FUNCTION_NAME_PREFIX)
    {
        $this->loggerFactory = $loggerFactory;
        $this->logger = $this->loggerFactory->loggerForClass(LogCategory::INFRASTRUCTURE, __NAMESPACE__, __CLASS__, __FILE__);
        $this->namePrefixForFramesToHide = $namePrefixForFramesToHide;
        $this->namePrefixForInternalFramesToHide = $namePrefixForInternalFramesToHide;
    }

    /**
     * @param int           $offset
     * @param ?positive-int $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function captureInApmFormat(int $offset, ?int $maxNumberOfFrames): array
    {
        $phpFormatFrames = debug_backtrace(/* options */ DEBUG_BACKTRACE_IGNORE_ARGS, /* limit */ $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames));
        return $this->convertPhpToApmFormat(IterableUtil::arraySuffix($phpFormatFrames, $offset), $maxNumberOfFrames);
    }

    /**
     * @param iterable<array<string, mixed>> $phpFormatFrames
     * @param ?positive-int                  $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     */
    public function convertPhpToApmFormat(iterable $phpFormatFrames, ?int $maxNumberOfFrames): array
    {
        $allClassicFormatFrames = $this->convertPhpToClassicFormat(
            null /* <- prevPhpFormatFrame */,
            $phpFormatFrames,
            $maxNumberOfFrames,
            false /* keepElasticApmFrames */,
            false /* $includeArgs */,
            false /* $includeThisObj */
        );

        return self::convertClassicToApmFormat($allClassicFormatFrames, $maxNumberOfFrames);
    }

    /**
     * @param int           $offset
     * @param ?positive-int $maxNumberOfFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function captureInClassicFormatExcludeElasticApm(int $offset = 0, ?int $maxNumberOfFrames = null): array
    {
        return $this->captureInClassicFormat($offset + 1, $maxNumberOfFrames, /* keepElasticApmFrames */ false);
    }

    /**
     * @param int           $offset
     * @param ?positive-int $maxNumberOfFrames
     * @param bool          $keepElasticApmFrames
     * @param bool          $includeArgs
     * @param bool          $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function captureInClassicFormat(int $offset = 0, ?int $maxNumberOfFrames = null, bool $keepElasticApmFrames = true, bool $includeArgs = false, bool $includeThisObj = false): array
    {
        $options = ($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0);
        return $this->convertCaptureToClassicFormat(
            // If there is non-null $maxNumberOfFrames we need to capture one more frame in PHP format
            debug_backtrace($options, /* limit */ $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames + 1)),
            // $offset + 1 to exclude the frame for the current method (captureInClassicFormat) call
            $offset + 1,
            $maxNumberOfFrames,
            $keepElasticApmFrames,
            $includeArgs,
            $includeThisObj
        );
    }

    /**
     * @param array<array<mixed>> $phpFormatFrames
     * @param int                 $offset
     * @param ?positive-int       $maxNumberOfFrames
     * @param bool                $keepElasticApmFrames
     * @param bool                $includeArgs
     * @param bool                $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function convertCaptureToClassicFormat(array $phpFormatFrames, int $offset, ?int $maxNumberOfFrames, bool $keepElasticApmFrames, bool $includeArgs, bool $includeThisObj): array
    {
        if ($offset >= count($phpFormatFrames)) {
            return [];
        }

        return $this->convertPhpToClassicFormat(
            $offset === 0 ? null : $phpFormatFrames[$offset - 1] /* <- prevPhpFormatFrame */,
            $offset === 0 ? $phpFormatFrames : IterableUtil::arraySuffix($phpFormatFrames, $offset),
            $maxNumberOfFrames,
            $keepElasticApmFrames,
            $includeArgs,
            $includeThisObj
        );
    }

    /**
     * @param ?array<mixed>          $prevPhpFormatFrame
     * @param iterable<array<mixed>> $phpFormatFrames
     * @param ?positive-int          $maxNumberOfFrames
     * @param bool                   $keepElasticApmFrames
     * @param bool                   $includeArgs
     * @param bool                   $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public function convertPhpToClassicFormat(
        ?array $prevPhpFormatFrame,
        iterable $phpFormatFrames,
        ?int $maxNumberOfFrames,
        bool $keepElasticApmFrames,
        bool $includeArgs,
        bool $includeThisObj
    ): array {
        $allClassicFormatFrames = [];
        $prevInFrame = $prevPhpFormatFrame;
        foreach ($phpFormatFrames as $currentInFrame) {
            $outFrame = new ClassicFormatStackTraceFrame();
            $isOutFrameEmpty = true;
            if ($prevInFrame !== null && $this->hasLocationPropertiesInPhpFormat($prevInFrame)) {
                $this->copyLocationPropertiesFromPhpToClassicFormat($prevInFrame, $outFrame);
                $isOutFrameEmpty = false;
            }
            if ($this->hasNonLocationPropertiesInPhpFormat($currentInFrame)) {
                $this->copyNonLocationPropertiesFromPhpToClassicFormat($currentInFrame, $includeArgs, $includeThisObj, $outFrame);
                $isOutFrameEmpty = false;
            }
            if (!$isOutFrameEmpty) {
                $allClassicFormatFrames[] = $outFrame;
            }
            $prevInFrame = $currentInFrame;
        }

        if ($prevInFrame !== null && $this->hasLocationPropertiesInPhpFormat($prevInFrame)) {
            $outFrame = new ClassicFormatStackTraceFrame();
            $this->copyLocationPropertiesFromPhpToClassicFormat($prevInFrame, $outFrame);
            $allClassicFormatFrames[] = $outFrame;
        }

        return $keepElasticApmFrames
            ? ($maxNumberOfFrames === null ? $allClassicFormatFrames : array_slice($allClassicFormatFrames, /* offset */ 0, $maxNumberOfFrames))
            : $this->excludeCodeToHide($allClassicFormatFrames, $maxNumberOfFrames);
    }


    /**
     * @param ClassicFormatStackTraceFrame[] $inFrames
     * @param ?positive-int                  $maxNumberOfFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    private function excludeCodeToHide(array $inFrames, ?int $maxNumberOfFrames): array
    {
        $outFrames = [];
        /** @var ?int $bufferedFromIndex */
        $bufferedFromIndex = null;
        foreach (RangeUtil::generateUpTo(count($inFrames)) as $currentInFrameIndex) {
            $currentInFrame = $inFrames[$currentInFrameIndex];
            if (self::isTrampolineCall($currentInFrame)) {
                if ($bufferedFromIndex === null) {
                    $bufferedFromIndex = $currentInFrameIndex;
                }
                continue;
            }

            if ($this->isCallToCodeToHide($currentInFrame)) {
                $bufferedFromIndex = null;
                continue;
            }

            for ($index = $bufferedFromIndex ?? $currentInFrameIndex; $index <= $currentInFrameIndex; ++$index) {
                self::addToOutputFrames($inFrames[$index], $maxNumberOfFrames, /* ref */ $outFrames);
            }
            $bufferedFromIndex = null;
        }

        return $outFrames;
    }

    public static function buildApmFormatFunctionForClassMethod(?string $classicName, ?bool $isStaticMethod, ?string $methodName): ?string
    {
        if ($methodName === null) {
            return null;
        }

        if ($classicName === null) {
            return $methodName;
        }

        $classMethodSep = ($isStaticMethod === null) ? '.' : ($isStaticMethod ? StackTraceUtil::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE : StackTraceUtil::FUNCTION_IS_METHOD_TYPE_VALUE);
        return $classicName . $classMethodSep . $methodName;
    }

    private static function isTrampolineCall(ClassicFormatStackTraceFrame $frame): bool
    {
        return $frame->class === null && $frame->isStaticMethod === null && ($frame->function === 'call_user_func' || $frame->function === 'call_user_func_array');
    }

    private function isCallToCodeToHide(ClassicFormatStackTraceFrame $frame): bool
    {
        return ($frame->class !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $frame->class))
               || ($frame->function !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $frame->function))
               || ($frame->function !== null && $frame->file === null && TextUtil::isPrefixOf($this->namePrefixForInternalFramesToHide, $frame->function));
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return ?bool
     */
    private function isStaticMethodInPhpFormat(array $frame): ?bool
    {
        if (($funcType = self::getNullableStringValue(StackTraceUtil::TYPE_KEY, $frame)) === null) {
            return null;
        }

        switch ($funcType) {
            case StackTraceUtil::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE:
                return true;
            case StackTraceUtil::FUNCTION_IS_METHOD_TYPE_VALUE:
                return false;
            default:
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Unexpected `' . StackTraceUtil::TYPE_KEY . '\' value', ['type' => $funcType]);
                return null;
        }
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?string
     */
    private function getNullableStringValue(string $key, array $phpFormatFormatFrame): ?string
    {
        /** @var ?string $value */
        $value = $this->getNullableValue($key, 'is_string', 'string', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?int
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableIntValue(string $key, array $phpFormatFormatFrame): ?int
    {
        /** @var ?int $value */
        $value = $this->getNullableValue($key, 'is_int', 'int', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?object
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableObjectValue(string $key, array $phpFormatFormatFrame): ?object
    {
        /** @var ?object $value */
        $value = $this->getNullableValue($key, 'is_object', 'object', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return null|mixed[]
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableArrayValue(string $key, array $phpFormatFormatFrame): ?array
    {
        /** @var ?array<mixed> $value */
        $value = $this->getNullableValue($key, 'is_array', 'array', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string                $key
     * @param callable(mixed): bool $isValueTypeFunc
     * @param string                $dbgExpectedType
     * @param array<string, mixed>  $phpFormatFormatFrame
     *
     * @return mixed
     */
    private function getNullableValue(string $key, callable $isValueTypeFunc, string $dbgExpectedType, array $phpFormatFormatFrame)
    {
        if (!array_key_exists($key, $phpFormatFormatFrame)) {
            return null;
        }

        $value = $phpFormatFormatFrame[$key];
        if ($value === null) {
            return null;
        }

        if (!$isValueTypeFunc($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Unexpected type for value under key (expected ' . $dbgExpectedType . ')',
                ['$key' => $key, 'value type' => DbgUtil::getType($value), 'value' => $value]
            );
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function hasNonLocationPropertiesInPhpFormat(array $frame): bool
    {
        return $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $frame) !== null;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function hasLocationPropertiesInPhpFormat(array $frame): bool
    {
        return $this->getNullableStringValue(StackTraceUtil::FILE_KEY, $frame) !== null;
    }

    private static function hasLocationPropertiesInClassicFormat(ClassicFormatStackTraceFrame $frame): bool
    {
        return $frame->file !== null;
    }

    /**
     * @param array<string, mixed>         $srcFrame
     * @param ClassicFormatStackTraceFrame $dstFrame
     */
    private function copyLocationPropertiesFromPhpToClassicFormat(array $srcFrame, ClassicFormatStackTraceFrame $dstFrame): void
    {
        $dstFrame->file = $this->getNullableStringValue(StackTraceUtil::FILE_KEY, $srcFrame);
        $dstFrame->line = $this->getNullableIntValue(StackTraceUtil::LINE_KEY, $srcFrame);
    }

    /**
     * @param array<string, mixed>         $srcFrame
     * @param bool                         $includeArgs
     * @param bool                         $includeThisObj
     * @param ClassicFormatStackTraceFrame $dstFrame
     */
    private function copyNonLocationPropertiesFromPhpToClassicFormat(array $srcFrame, bool $includeArgs, bool $includeThisObj, ClassicFormatStackTraceFrame $dstFrame): void
    {
        $dstFrame->class = $this->getNullableStringValue(StackTraceUtil::CLASS_KEY, $srcFrame);
        $dstFrame->function = $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $srcFrame);
        $dstFrame->isStaticMethod = $this->isStaticMethodInPhpFormat($srcFrame);
        if ($includeThisObj) {
            $dstFrame->thisObj = $this->getNullableObjectValue(StackTraceUtil::THIS_OBJECT_KEY, $srcFrame);
        }
        if ($includeArgs) {
            $dstFrame->args = $this->getNullableArrayValue(StackTraceUtil::ARGS_KEY, $srcFrame);
        }
    }

    /**
     * @template TOutputFrame
     *
     * @param TOutputFrame    $frameToAdd
     * @param ?int            $maxNumberOfFrames
     * @param TOutputFrame[] &$outputFrames
     *
     * @return bool
     *
     * @phpstan-param null|positive-int $maxNumberOfFrames
     */
    private static function addToOutputFrames($frameToAdd, ?int $maxNumberOfFrames, /* ref */ array &$outputFrames): bool
    {
        $outputFrames[] = $frameToAdd;
        return (count($outputFrames) !== $maxNumberOfFrames);
    }

    /**
     * @param Throwable     $throwable
     * @param ?positive-int $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     */
    public function convertThrowableTraceToApmFormat(Throwable $throwable, ?int $maxNumberOfFrames): array
    {
        $frameForThrowLocation = [StackTraceUtil::FILE_KEY => $throwable->getFile(), StackTraceUtil::LINE_KEY => $throwable->getLine()];
        return $this->convertPhpToApmFormat(IterableUtil::prepend($frameForThrowLocation, $throwable->getTrace()), $maxNumberOfFrames);
    }

    // TODO: Sergey Kleyman: REMOVE:
    // /**
    //  * @param iterable<ClassicFormatStackTraceFrame> $inFrames
    //  * @param ?positive-int                          $maxNumberOfFrames
    //  *
    //  * @return StackTraceFrame[]
    //  */
    // private static function convertClassicToApmFormat(iterable $inFrames, ?int $maxNumberOfFrames): array
    // {
    //     /** @var StackTraceFrame[] $outFrames */
    //     $outFrames = [];
    //
    //     /** @var ?ClassicFormatStackTraceFrame $prevInFrame */
    //     $prevInFrame = null;
    //     foreach ($inFrames as $currentInFrame) {
    //         if ($currentInFrame->file === null) {
    //             $isOutFrameEmpty = true;
    //             $outFrame = new StackTraceFrame(self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
    //         } else {
    //             $isOutFrameEmpty = false;
    //             $outFrame = new StackTraceFrame($currentInFrame->file, $currentInFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
    //         }
    //         if ($prevInFrame !== null && $prevInFrame->function !== null) {
    //             $isOutFrameEmpty = false;
    //             $outFrame->function = self::buildApmFormatFunctionForClassMethod($prevInFrame->class, $prevInFrame->isStaticMethod, $prevInFrame->function);
    //         }
    //         if (!$isOutFrameEmpty && !self::addToOutputFrames($outFrame, $maxNumberOfFrames, /* ref */ $outFrames)) {
    //             break;
    //         }
    //         $prevInFrame = $currentInFrame;
    //     }
    //
    //     return $outFrames;
    // }

    /**
     * @param iterable<ClassicFormatStackTraceFrame> $inputFrames
     * @param ?positive-int                          $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     */
    public static function convertClassicToApmFormat(iterable $inputFrames, ?int $maxNumberOfFrames): array
    {
        $outputFrames = [];
        /** @var ?ClassicFormatStackTraceFrame $prevInputFrame */
        $prevInputFrame = null;
        $exitedEarly = false;
        foreach ($inputFrames as $currentInputFrame) {
            if ($prevInputFrame === null) {
                if (self::hasLocationPropertiesInClassicFormat($currentInputFrame)) {
                    $outputFrame = new StackTraceFrame($currentInputFrame->file ?? self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, $currentInputFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
                    if (!self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames)) {
                        $exitedEarly = true;
                        break;
                    }
                }
                $prevInputFrame = $currentInputFrame;
                continue;
            }

            $outputFrame = new StackTraceFrame($currentInputFrame->file ?? self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, $currentInputFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
            $outputFrame->function = StackTraceUtil::buildApmFormatFunctionForClassMethod($prevInputFrame->class, $prevInputFrame->isStaticMethod, $prevInputFrame->function);
            if (!self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames)) {
                $exitedEarly = true;
                break;
            }

            $prevInputFrame = $currentInputFrame;
        }

        if (!$exitedEarly && $prevInputFrame !== null && $prevInputFrame->function !== null) {
            $outputFrame = new StackTraceFrame(
                self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE,
                self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE,
                StackTraceUtil::buildApmFormatFunctionForClassMethod($prevInputFrame->class, $prevInputFrame->isStaticMethod, $prevInputFrame->function)
            );
            self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames);
        }

        return $outputFrames;
    }

    /**
     * @param int $stackTraceLimit
     *
     * @return ?int
     * @phpstan-return null|0|positive-int
     */
    public static function convertLimitConfigToMaxNumberOfFrames(int $stackTraceLimit): ?int
    {
        /**
         * stack_trace_limit
         *      0 - stack trace collection should be disabled
         *      any positive value - the value is the maximum number of frames to collect
         *      any negative value - all frames should be collected
         */
        return $stackTraceLimit < 0 ? null : $stackTraceLimit;
    }
}
