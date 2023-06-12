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

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var Logger */
    private $logger;

    /** @var string */
    private $namePrefixForFramesToHide;

    public function __construct(LoggerFactory $loggerFactory, string $namePrefixForFramesToHide = self::ELASTIC_APM_FQ_NAME_PREFIX)
    {
        $this->loggerFactory = $loggerFactory;
        $this->logger = $this->loggerFactory->loggerForClass(LogCategory::INFRASTRUCTURE, __NAMESPACE__, __CLASS__, __FILE__);
        $this->namePrefixForFramesToHide = $namePrefixForFramesToHide;
    }

    /**
     * @param int $offset
     * @param ?int $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     *
     * @noinspection PhpVarTagWithoutVariableNameInspection
     *
     * @phpstan-param 0|positive-int      $offset
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     */
    public function captureInApmFormat(int $offset, ?int $maxNumberOfFrames): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        $phpFormatFrames = debug_backtrace(/* options */ DEBUG_BACKTRACE_IGNORE_ARGS, /* limit */ $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames));
        return $this->convertPhpToApmFormat(IterableUtil::arraySuffix($phpFormatFrames, $offset), $maxNumberOfFrames);
    }

    /**
     * @param iterable<array<string, mixed>> $inputFrames
     * @param ?int                           $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     *
     * @noinspection PhpVarTagWithoutVariableNameInspection
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     */
    public function convertPhpToApmFormat(iterable $inputFrames, ?int $maxNumberOfFrames): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        /** @var StackTraceFrame[] $outputFrames */
        $outputFrames = [];
        $this->excludeCodeToHide(
            $inputFrames,
            /**
             * @param array<string, mixed>  $inputFrameWithLocationData
             * @param ?array<string, mixed> $inputFrameWithNonLocationData
             */
            function (array $inputFrameWithLocationData, ?array $inputFrameWithNonLocationData) use ($maxNumberOfFrames, &$outputFrames): bool {
                $outputFrameFunc = null;
                if ($inputFrameWithNonLocationData !== null) {
                    $outputFrameFunc = StackTraceUtil::buildApmFormatFunctionForClassMethod(
                        $this->getNullableStringValue(StackTraceUtil::CLASS_KEY, $inputFrameWithNonLocationData),
                        $this->isStaticMethodInPhpFormat($inputFrameWithNonLocationData),
                        $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $inputFrameWithNonLocationData)
                    );
                }

                $file = $this->getNullableStringValue(StackTraceUtil::FILE_KEY, $inputFrameWithLocationData);
                $line = $this->getNullableIntValue(StackTraceUtil::LINE_KEY, $inputFrameWithLocationData);
                $outputFrame = new StackTraceFrame($file ?? StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, $line ?? StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
                $outputFrame->function = $outputFrameFunc;
                return self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames);
            }
        );

        return $outputFrames;
    }

    /**
     * @param int  $offset
     * @param ?int $maxNumberOfFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @noinspection PhpVarTagWithoutVariableNameInspection
     *
     * @phpstan-param 0|positive-int      $offset
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     */
    public function captureInClassicFormatExcludeElasticApm(int $offset = 0, ?int $maxNumberOfFrames = null): array
    {
        return $this->captureInClassicFormat($offset + 1, $maxNumberOfFrames, /* keepElasticApmFrames */ false);
    }

    /**
     * @param int  $offset
     * @param ?int $maxNumberOfFrames
     * @param bool $keepElasticApmFrames
     * @param bool $includeArgs
     * @param bool $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @noinspection       PhpVarTagWithoutVariableNameInspection
     *
     * @phpstan-param 0|positive-int      $offset
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     */
    public function captureInClassicFormat(int $offset = 0, ?int $maxNumberOfFrames = null, bool $keepElasticApmFrames = true, bool $includeArgs = false, bool $includeThisObj = false): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        $options = ($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0);
        // If there is non-null $maxNumberOfFrames we need to capture one more frame in PHP format
        $phpFormatFrames = debug_backtrace($options, /* limit */ $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames + 1));
        $phpFormatFrames = IterableUtil::arraySuffix($phpFormatFrames, $offset);

        /** @var ClassicFormatStackTraceFrame[] $outputFrames */
        $outputFrames = [];
        $isTopFrame = true;
        /** @var ?array<string, mixed> $bufferedBeforeTopFrame */
        $bufferedBeforeTopFrame = null;
        /** @var ?array<string, mixed> $prevFrame */
        $prevFrame = null;
        $hasExitedLoopEarly = false;
        if ($keepElasticApmFrames) {
            foreach ($phpFormatFrames as $currentFrame) {
                if ($prevFrame === null) {
                    $prevFrame = $currentFrame;
                    continue;
                }
                if (!$this->captureInClassicFormatConsume($maxNumberOfFrames, $includeArgs, $includeThisObj, $prevFrame, $currentFrame, $bufferedBeforeTopFrame, $isTopFrame, $outputFrames)) {
                    $hasExitedLoopEarly = true;
                    break;
                }
                $prevFrame = $currentFrame;
            }
        } else {
            $this->excludeCodeToHide(
                $phpFormatFrames,
                /**
                 * @param array<string, mixed>  $inputFrameWithLocationData
                 * @param ?array<string, mixed> $inputFrameWithNonLocationData
                 *
                 * @return bool
                 */
                function (
                    array $inputFrameWithLocationData,
                    ?array $inputFrameWithNonLocationData
                ) use (
                    $maxNumberOfFrames,
                    $includeArgs,
                    $includeThisObj,
                    &$prevFrame,
                    &$hasExitedLoopEarly,
                    &$bufferedBeforeTopFrame,
                    &$isTopFrame,
                    &$outputFrames
                ): bool {
                    $currentFrame = $this->mergePhpFrames($inputFrameWithLocationData, $inputFrameWithNonLocationData, $includeArgs, $includeThisObj);
                    if ($prevFrame === null) {
                        $prevFrame = $currentFrame;
                        return true;
                    }
                    if (!$this->captureInClassicFormatConsume($maxNumberOfFrames, $includeArgs, $includeThisObj, $prevFrame, $currentFrame, $bufferedBeforeTopFrame, $isTopFrame, $outputFrames)) {
                        $hasExitedLoopEarly = true;
                        return false;
                    }
                    $prevFrame = $currentFrame;
                    return true;
                }
            );
        }

        if (!$hasExitedLoopEarly && $prevFrame !== null) {
            $this->captureInClassicFormatConsume($maxNumberOfFrames, $includeArgs, $includeThisObj, $prevFrame, /* nextInputFrame */ null, $bufferedBeforeTopFrame, $isTopFrame, $outputFrames);
        }

        return $outputFrames;
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

    /**
     * @param array<string, mixed> $inputFrame
     *
     * @return bool
     */
    private function isTrampolineCall(array $inputFrame): bool
    {
        $func = $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $inputFrame);
        if ($func !== 'call_user_func' && $func !== 'call_user_func_array') {
            return false;
        }

        $class = $this->getNullableStringValue(StackTraceUtil::CLASS_KEY, $inputFrame);
        if ($class !== null) {
            return false;
        }

        $funcType = $this->getNullableStringValue(StackTraceUtil::TYPE_KEY, $inputFrame);
        if ($funcType !== null) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $inputFrame
     *
     * @return bool
     */
    private function isCallToCodeToHide(array $inputFrame): bool
    {
        $class = $this->getNullableStringValue(StackTraceUtil::CLASS_KEY, $inputFrame);
        if ($class !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $class)) {
            return true;
        }

        $func = $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $inputFrame);
        if ($func !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $func)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed>                                        $inputFrameWithLocationData
     * @param ?array<string, mixed>                                       $higherInputFrameWithNonLocationData
     * @param callable(array<string, mixed>, ?array<string, mixed>): bool $consumeCallback
     *
     * @return bool
     */
    private function excludeCodeToHideProcessBufferedFrame(array $inputFrameWithLocationData, ?array $higherInputFrameWithNonLocationData, callable $consumeCallback): bool
    {
        $func = null;
        $frameWithNonLocationData = $this->isCallToCodeToHide($inputFrameWithLocationData) ? $higherInputFrameWithNonLocationData : $inputFrameWithLocationData;
        if ($frameWithNonLocationData !== null) {
            $func = $this->getNullableStringValue(StackTraceUtil::FUNCTION_KEY, $frameWithNonLocationData);
        }

        if ($this->getNullableStringValue(StackTraceUtil::FILE_KEY, $inputFrameWithLocationData) == null && $func === null) {
            return true;
        }

        return $consumeCallback($inputFrameWithLocationData, $frameWithNonLocationData);
    }

    /**
     * @param array<string, mixed>[]                                      $bufferedInFrames
     * @param ?array<string, mixed>                                       $higherInputFrameWithNonLocationData
     * @param callable(array<string, mixed>, ?array<string, mixed>): bool $consumeCallback
     *
     * @return bool
     */
    private function excludeCodeToHideProcessBufferedFrames(array $bufferedInFrames, ?array $higherInputFrameWithNonLocationData, callable $consumeCallback): bool
    {
        if (!$this->excludeCodeToHideProcessBufferedFrame($bufferedInFrames[0], $higherInputFrameWithNonLocationData, $consumeCallback)) {
            return false;
        }
        foreach (RangeUtil::generateFromToIncluding(1, count($bufferedInFrames) - 1) as $bufferedInFramesIndex) {
            if (!$this->excludeCodeToHideProcessBufferedFrame($bufferedInFrames[$bufferedInFramesIndex], /* higherInputFrameWithNonLocationData */ null, $consumeCallback)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param iterable<array<string, mixed>>                              $inputFrames
     * @param callable(array<string, mixed>, ?array<string, mixed>): bool $consumeCallback
     */
    private function excludeCodeToHide(iterable $inputFrames, callable $consumeCallback): void
    {
        /** @var array<string, mixed>[] $bufferedInFrames */
        $bufferedInFrames = [];
        /** @var ?array<string, mixed> $higherInFrameWithNonLocationData */
        $higherInFrameWithNonLocationData = null;
        foreach ($inputFrames as $currentInFrame) {
            if (ArrayUtil::isEmpty($bufferedInFrames)) {
                $bufferedInFrames[] = $currentInFrame;
                continue;
            }

            if ($this->isTrampolineCall($currentInFrame)) {
                $bufferedInFrames[] = $currentInFrame;
                continue;
            }

            if ($this->isCallToCodeToHide($currentInFrame)) {
                if (!$this->isCallToCodeToHide($bufferedInFrames[0])) {
                    $higherInFrameWithNonLocationData = $bufferedInFrames[0];
                }
            } else {
                if (!$this->excludeCodeToHideProcessBufferedFrames($bufferedInFrames, $higherInFrameWithNonLocationData, $consumeCallback)) {
                    return;
                }
                $higherInFrameWithNonLocationData = null;
            }

            $bufferedInFrames = [$currentInFrame];
        }

        if (!ArrayUtil::isEmpty($bufferedInFrames)) {
            $this->excludeCodeToHideProcessBufferedFrames($bufferedInFrames, $higherInFrameWithNonLocationData, $consumeCallback);
        }
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
     * @param array<string, mixed>  $frameWithLocationData
     * @param ?array<string, mixed> $frameWithNonLocationData
     *
     * @return array<string, mixed>
     */
    private function mergePhpFrames(array $frameWithLocationData, ?array $frameWithNonLocationData, bool $includeArgs, bool $includeThisObj): array
    {
        $result = [];
        foreach ([StackTraceUtil::FILE_KEY, StackTraceUtil::LINE_KEY] as $key) {
            if (array_key_exists($key, $frameWithLocationData)) {
                $result[$key] = $frameWithLocationData[$key];
            }
        }

        if ($frameWithNonLocationData !== null) {
            $keys = [StackTraceUtil::CLASS_KEY, StackTraceUtil::FUNCTION_KEY, StackTraceUtil::TYPE_KEY];
            if ($includeThisObj) {
                $keys[] = StackTraceUtil::THIS_OBJECT_KEY;
            }
            if ($includeArgs) {
                $keys[] = StackTraceUtil::ARGS_KEY;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $frameWithNonLocationData)) {
                    $result[$key] = $frameWithNonLocationData[$key];
                } else {
                    unset($frameWithNonLocationData[$key]);
                }
            }
        }
        return $result;
    }

    /**
     * @param ?int                            $maxNumberOfFrames
     * @param bool                            $includeArgs
     * @param bool                            $includeThisObj
     * @param array<string, mixed>            $currentInputFrame
     * @param ?array<string, mixed>           $nextInputFrame
     * @param ?array<string, mixed>          &$bufferedBeforeTopFrame
     * @param bool                           &$isTopFrame
     * @param ClassicFormatStackTraceFrame[] &$outputFrames
     *
     * @return bool
     *
     * @phpstan-param null|positive-int $maxNumberOfFrames
     */
    private function captureInClassicFormatConsume(
        ?int $maxNumberOfFrames,
        bool $includeArgs,
        bool $includeThisObj,
        array $currentInputFrame,
        ?array $nextInputFrame,
        ?array &$bufferedBeforeTopFrame,
        bool &$isTopFrame,
        array &$outputFrames
    ): bool {
        if ($isTopFrame) {
            if ($bufferedBeforeTopFrame === null) {
                $bufferedBeforeTopFrame = $currentInputFrame;
                return true;
            }

            $isTopFrame = false;
            if ($this->hasNonLocationPropertiesInPhpFormat($currentInputFrame)) {
                $outputFrame = new ClassicFormatStackTraceFrame();
                $this->copyNonLocationPropertiesFromPhpToClassicFormat($currentInputFrame, $includeArgs, $includeThisObj, $outputFrame);
                $this->copyLocationPropertiesFromPhpToClassicFormat($bufferedBeforeTopFrame, $outputFrame);
                if (!self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames)) {
                    return false;
                }
            }
        }

        $outputFrame = new ClassicFormatStackTraceFrame();
        $this->copyLocationPropertiesFromPhpToClassicFormat($currentInputFrame, $outputFrame);
        if ($nextInputFrame !== null) {
            $this->copyNonLocationPropertiesFromPhpToClassicFormat($nextInputFrame, $includeArgs, $includeThisObj, $outputFrame);
        }
        return self::addToOutputFrames($outputFrame, $maxNumberOfFrames, /* ref */ $outputFrames);
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
     * @param Throwable $throwable
     * @param ?int      $maxNumberOfFrames
     *
     * @return StackTraceFrame[]
     *
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     */
    public function convertThrowableTraceToApmFormat(Throwable $throwable, ?int $maxNumberOfFrames): array
    {
        $frameForThrowLocation = [StackTraceUtil::FILE_KEY => $throwable->getFile(), StackTraceUtil::LINE_KEY => $throwable->getLine()];
        return $this->convertPhpToApmFormat(IterableUtil::prepend($frameForThrowLocation, $throwable->getTrace()), $maxNumberOfFrames);
    }

    /**
     * @param iterable<ClassicFormatStackTraceFrame> $inputFrames
     *
     * @return StackTraceFrame[]
     *
     * @phpstan-param null|0|positive-int $maxNumberOfFrames
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function convertClassicToApmFormat(iterable $inputFrames, ?int $maxNumberOfFrames): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        $outputFrames = [];
        /** @var ?ClassicFormatStackTraceFrame $prevInputFrame */
        $prevInputFrame = null;
        $exitedEarly = false;
        foreach ($inputFrames as $currentInputFrame) {
            if ($prevInputFrame === null) {
                if ($currentInputFrame->file !== null) {
                    $outputFrame = new StackTraceFrame($currentInputFrame->file, $currentInputFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
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
}
