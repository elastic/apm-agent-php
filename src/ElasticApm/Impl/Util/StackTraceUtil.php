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

    private const FILE_NAME_NOT_AVAILABLE_SUBSTITUTE = 'FILE NAME N/A';
    private const LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE = 0;

    /** @var bool */
    private static $triedToBuildCachedElasticApmFilePrefix = false;

    /** @var ?string */
    private static $cachedElasticApmFilePrefix = null;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var Logger */
    private $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->loggerFactory = $loggerFactory;
        $this->logger = $this->loggerFactory->loggerForClass(LogCategory::INFRASTRUCTURE, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @return ClassicFormatStackTraceFrameOld[]
     */
    public function captureInClassicFormatExcludeElasticApm(int $offset = 0): array
    {
        return $this->captureInClassicFormat($offset + 1, /* framesCountLimit */ null, /* keepElasticApmFrames */ false);
    }

    /**
     * @return ClassicFormatStackTraceFrameOld[]
     */
    public function captureInClassicFormat(int $offset = 0, ?int $maxNumberOfFrames = null, bool $keepElasticApmFrames = true, bool $includeArgs = false, bool $includeThisObj = false): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        // If there is non-null $maxNumberOfFrames we need to capture one more frame in PHP format
        $phpFormatFrames = $this->captureInPhpFormat($offset + 1, $maxNumberOfFrames === null ? null : ($maxNumberOfFrames + 1), $includeArgs, $includeThisObj);
        $classicFormatFrames = self::convertPhpToClassicFormat($phpFormatFrames);
        $classicFormatFrames = $maxNumberOfFrames === null ? $classicFormatFrames : array_slice($classicFormatFrames, /* offset */ 0, $maxNumberOfFrames);

        return $keepElasticApmFrames ? $classicFormatFrames : self::excludeElasticApmInClassicFormat($classicFormatFrames);
    }

    /**
     * @return PhpFormatStackTraceFrame[]
     */
    public function captureInPhpFormat(int $offset = 0, ?int $maxNumberOfFrames = null, bool $includeArgs = false, bool $includeThisObj = false): array
    {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        $options = ($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0);

        $srcFrames = debug_backtrace($options, $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames));
        if (count($srcFrames) <= $offset) {
            return [];
        }
        $srcFrames = array_slice($srcFrames, $offset);
        $srcFramesCount = count($srcFrames);
        if ($srcFramesCount !== 0) {
            // Only location properties shoud be kept in the top frame
            $topFrameRef = &$srcFrames[0];
            unset($topFrameRef[self::CLASS_KEY]);
            unset($topFrameRef[self::FUNCTION_KEY]);
            unset($topFrameRef[self::TYPE_KEY]);
            unset($topFrameRef[self::ARGS_KEY]);
            unset($topFrameRef[self::THIS_OBJECT_KEY]);

            // It seems that sometimes the bottom frame include args even when DEBUG_BACKTRACE_IGNORE_ARGS is set
            if ((!$includeArgs) && array_key_exists(self::ARGS_KEY, ($bottomFrameRef = &$srcFrames[$srcFramesCount - 1]))) {
                unset($bottomFrameRef[self::ARGS_KEY]);
                unset($bottomFrameRef);
            }
        }

        return self::convertDebugBacktraceToPhpFormat($srcFrames, /* includeValuesOnStack */ $includeArgs || $includeThisObj);
    }

    /**
     * @param array<string, mixed>[] $srcFrames
     * @param bool                   $includeValuesOnStack
     *
     * @return PhpFormatStackTraceFrame[]
     */
    public function convertDebugBacktraceToPhpFormat(array $srcFrames, bool $includeValuesOnStack): array
    {
        /** @var PhpFormatStackTraceFrame[] $result */
        $result = [];
        foreach ($srcFrames as $srcFrame) {
            $newFrame = new PhpFormatStackTraceFrame();
            $newFrame->copyDataFromFromDebugBacktraceFrame($srcFrame, $includeValuesOnStack, $this->loggerFactory);
            $result[] = $newFrame;
        }
        return $result;
    }

    /**
     * @param PhpFormatStackTraceFrame[] $inFrames
     *
     * @return ClassicFormatStackTraceFrameOld[]
     */
    public static function convertPhpToClassicFormat(array $inFrames): array
    {
        $inFramesCount = count($inFrames);
        if ($inFramesCount === 0) {
            return [];
        }
        /** @var ClassicFormatStackTraceFrameOld[] $outFrames */
        $outFrames = [];
        $inFramesTop = $inFrames[0];
        if ($inFramesTop->hasNonLocationProperties()) {
            $outFramesTop = new ClassicFormatStackTraceFrameOld();
            $outFramesTop->copyNonLocationPropertiesFrom($inFramesTop);
            $outFrames[] = $outFramesTop;
        }
        foreach (RangeUtil::generateUpTo($inFramesCount) as $inFramesIndex) {
            $outFrame = new ClassicFormatStackTraceFrameOld();
            $outFrame->copyLocationPropertiesFrom($inFrames[$inFramesIndex]);

            if ($inFramesIndex + 1 < $inFramesCount) {
                $outFrame->copyNonLocationPropertiesFrom($inFrames[$inFramesIndex + 1]);
            }
            $outFrames[] = $outFrame;
        }
        return $outFrames;
    }

    /**
     * @param ClassicFormatStackTraceFrameOld[] $inFrames
     *
     * @return PhpFormatStackTraceFrame[]
     */
    public static function convertClassicToPhpFormat(array $inFrames): array
    {
        $inFramesCount = count($inFrames);
        if ($inFramesCount === 0) {
            return [];
        }
        /** @var PhpFormatStackTraceFrame[] $outFrames */
        $outFrames = [];
        $inFramesTop = $inFrames[0];
        if ($inFramesTop->hasLocationProperties()) {
            $outFramesTop = new PhpFormatStackTraceFrame();
            $outFramesTop->copyLocationPropertiesFrom($inFramesTop);
            $outFrames[] = $outFramesTop;
        }
        foreach (RangeUtil::generateUpTo($inFramesCount - 1) as $inFramesIndex) {
            $outFrame = new PhpFormatStackTraceFrame();
            $outFrame->copyNonLocationPropertiesFrom($inFrames[$inFramesIndex]);
            $outFrame->copyLocationPropertiesFrom($inFrames[$inFramesIndex + 1]);
            $outFrames[] = $outFrame;
        }
        return $outFrames;
    }

    private static function buildElasticApmFilePrefix(): ?string
    {
        $thisSrcFileDir = __DIR__;
        $elasticApmSubDir = DIRECTORY_SEPARATOR . 'ElasticApm' . DIRECTORY_SEPARATOR;

        if (($pos = strpos($thisSrcFileDir, $elasticApmSubDir)) !== false) {
            return substr($thisSrcFileDir, /* offset */ 0, /* length */ $pos + strlen($elasticApmSubDir));
        }

        $elasticApmSubDir = 'ElasticApm' . DIRECTORY_SEPARATOR;
        if (TextUtil::isPrefixOf($elasticApmSubDir, $thisSrcFileDir)) {
            return $elasticApmSubDir;
        }

        return null;
    }

    /**
     * @param ClassicFormatStackTraceFrameOld $frame
     *
     * @return bool
     */
    private static function isElasticApmFrameInClassicFormat(ClassicFormatStackTraceFrameOld $frame): bool
    {
        if ($frame->file !== null) {
            if (!self::$triedToBuildCachedElasticApmFilePrefix) {
                self::$cachedElasticApmFilePrefix = self::buildElasticApmFilePrefix();
            }
            if (self::$cachedElasticApmFilePrefix !== null) {
                return TextUtil::isPrefixOf(self::$cachedElasticApmFilePrefix, $frame->file);
            }
        }
        if ($frame->class !== null) {
            return TextUtil::isPrefixOf('Elastic\\Apm\\', $frame->class) || TextUtil::isPrefixOf('\\Elastic\\Apm\\', $frame->class);
        }
        return false;
    }

    /**
     * @param ClassicFormatStackTraceFrameOld[] $inFrames
     *
     * @return ClassicFormatStackTraceFrameOld[]
     */
    public static function excludeElasticApmInClassicFormat(array $inFrames): array
    {
        $result = [];
        foreach ($inFrames as $inFrame) {
            if (!self::isElasticApmFrameInClassicFormat($inFrame)) {
                $result[] = $inFrame;
            }
        }
        return $result;
    }

    public static function buildFunctionNameForClassMethod(?string $classicName, ?bool $isStaticMethod, ?string $methodName): ?string
    {
        if ($methodName === null) {
            return null;
        }

        if ($classicName === null) {
            return $methodName;
        }

        $classMethodSep = ($isStaticMethod === null) ? '.' : ($isStaticMethod ? self::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE : self::FUNCTION_IS_METHOD_TYPE_VALUE);
        return $classicName . $classMethodSep . $methodName;
    }

    /**
     * @param PhpFormatStackTraceFrame[] $phpFormatFrames
     *
     * @return StackTraceFrame[]
     */
    public static function convertPhpToApmFormat(array $phpFormatFrames): array
    {
        $result = [];
        foreach ($phpFormatFrames as $phpFormatFrame) {
            $newFrame = new StackTraceFrame($phpFormatFrame->file ?? self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, $phpFormatFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
            $newFrame->function = self::buildFunctionNameForClassMethod($phpFormatFrame->class, $phpFormatFrame->isStaticMethod, $phpFormatFrame->function);
            $result[] = $newFrame;
        }
        return $result;
    }

    /**
     * @param ClassicFormatStackTraceFrameOld[] $classicFormatFrames
     *
     * @return StackTraceFrame[]
     */
    public static function convertClassicToApmFormat(array $classicFormatFrames): array
    {
        return self::convertPhpToApmFormat(self::convertClassicToPhpFormat($classicFormatFrames));
    }

    /**
     * @param int $offset
     *
     * @return StackTraceFrame[]
     */
    public function captureInApmFormat(int $offset): array
    {
        return self::convertClassicToApmFormat($this->captureInClassicFormat($offset + 1, /* $maxNumberOfFrames */ null, /* $keepElasticApmFrames */ false));
    }

    /**
     * @param array<string, mixed>[] $debugBacktraceFormatFrames
     *
     * @return StackTraceFrame[]
     */
    public function convertExternallyCapturedToApmFormat(array $debugBacktraceFormatFrames): array
    {
        $phpFormatFrames = $this->convertDebugBacktraceToPhpFormat($debugBacktraceFormatFrames, /* includeValuesOnStack */ false);
        $classicFormatFrames = self::convertPhpToClassicFormat($phpFormatFrames);
        return self::convertClassicToApmFormat(self::excludeElasticApmInClassicFormat($classicFormatFrames));
    }

    /**
     * @param Throwable $throwable
     *
     * @return StackTraceFrame[]
     */
    public function convertThrowableTraceToApmFormat(Throwable $throwable): array
    {
        $debugBacktraceFormatFrames = $throwable->getTrace();
        $frameForThrowLocation = [StackTraceUtil::FILE_KEY => $throwable->getFile(), StackTraceUtil::LINE_KEY => $throwable->getLine()];
        array_unshift(/* ref */ $debugBacktraceFormatFrames, $frameForThrowLocation);
        return $this->convertExternallyCapturedToApmFormat($debugBacktraceFormatFrames);
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return ?string
     */
    private function getNullableStringValue(string $key, array $debugBacktraceFormatFrame): ?string
    {
        /** @var ?string $value */
        $value = $this->getNullableValue($key, 'is_string', 'string', $debugBacktraceFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return ?int
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableIntValue(string $key, array $debugBacktraceFormatFrame): ?int
    {
        /** @var ?int $value */
        $value = $this->getNullableValue($key, 'is_int', 'int', $debugBacktraceFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return ?object
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableObjectValue(string $key, array $debugBacktraceFormatFrame): ?object
    {
        /** @var ?object $value */
        $value = $this->getNullableValue($key, 'is_object', 'object', $debugBacktraceFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return null|mixed[]
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableArrayValue(string $key, array $debugBacktraceFormatFrame): ?array
    {
        /** @var ?array<mixed> $value */
        $value = $this->getNullableValue($key, 'is_array', 'array', $debugBacktraceFormatFrame);
        return $value;
    }

    /**
     * @param string                $key
     * @param callable(mixed): bool $isValueTypeFunc
     * @param string                $dbgExpectedType
     * @param array<string, mixed>  $debugBacktraceFormatFrame
     *
     * @return mixed
     */
    private function getNullableValue(string $key, callable $isValueTypeFunc, string $dbgExpectedType, array $debugBacktraceFormatFrame)
    {
        if (!array_key_exists($key, $debugBacktraceFormatFrame)) {
            return null;
        }

        $value = $debugBacktraceFormatFrame[$key];
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
}
