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
    use StaticClassTrait;

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

    /**
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function captureInClassicFormatExcludeElasticApm(LoggerFactory $loggerFactory, int $offset = 0): array
    {
        return self::captureInClassicFormat($loggerFactory, $offset + 1, /* framesCountLimit */ null, /* includeElasticApmFrames */ false);
    }

    /**
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function captureInClassicFormat(
        LoggerFactory $loggerFactory,
        int $offset = 0,
        ?int $maxNumberOfFrames = null,
        bool $includeElasticApmFrames = true,
        bool $includeArgs = false,
        bool $includeThisObj = false
    ): array {
        if ($maxNumberOfFrames === 0) {
            return [];
        }

        // If there is non-null $maxNumberOfFrames we need to capture one more frame in PHP format
        $phpFormatFrames = self::captureInPhpFormat($loggerFactory, $offset + 1, $maxNumberOfFrames === null ? null : ($maxNumberOfFrames + 1), $includeArgs, $includeThisObj);
        $classicFormatFrames = self::convertPhpToClassicFormat($phpFormatFrames);
        $classicFormatFrames = $maxNumberOfFrames === null ? $classicFormatFrames : array_slice($classicFormatFrames, /* offset */ 0, $maxNumberOfFrames);

        return $includeElasticApmFrames ? $classicFormatFrames : self::excludeElasticApmInClassicFormat($classicFormatFrames);
    }

    /**
     * @return PhpFormatStackTraceFrame[]
     */
    public static function captureInPhpFormat(LoggerFactory $loggerFactory, int $offset = 0, ?int $maxNumberOfFrames = null, bool $includeArgs = false, bool $includeThisObj = false): array
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

        return self::convertDebugBacktraceToPhpFormat($srcFrames, /* includeValuesOnStack */ $includeArgs || $includeThisObj, $loggerFactory);
    }

    /**
     * @param array<string, mixed>[] $srcFrames
     * @param bool                   $includeValuesOnStack
     * @param LoggerFactory          $loggerFactory
     *
     * @return PhpFormatStackTraceFrame[]
     */
    public static function convertDebugBacktraceToPhpFormat(array $srcFrames, bool $includeValuesOnStack, LoggerFactory $loggerFactory): array
    {
        /** @var PhpFormatStackTraceFrame[] $result */
        $result = [];
        foreach ($srcFrames as $srcFrame) {
            $newFrame = new PhpFormatStackTraceFrame();
            $newFrame->copyDataFromFromDebugBacktraceFrame($srcFrame, $includeValuesOnStack, $loggerFactory);
            $result[] = $newFrame;
        }
        return $result;
    }

    /**
     * @param PhpFormatStackTraceFrame[] $inFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function convertPhpToClassicFormat(array $inFrames): array
    {
        $inFramesCount = count($inFrames);
        if ($inFramesCount === 0) {
            return [];
        }
        /** @var ClassicFormatStackTraceFrame[] $outFrames */
        $outFrames = [];
        $inFramesTop = $inFrames[0];
        if ($inFramesTop->hasNonLocationProperties()) {
            $outFramesTop = new ClassicFormatStackTraceFrame();
            $outFramesTop->copyNonLocationPropertiesFrom($inFramesTop);
            $outFrames[] = $outFramesTop;
        }
        foreach (RangeUtil::generateUpTo($inFramesCount) as $inFramesIndex) {
            $outFrame = new ClassicFormatStackTraceFrame();
            $outFrame->copyLocationPropertiesFrom($inFrames[$inFramesIndex]);

            if ($inFramesIndex + 1 < $inFramesCount) {
                $outFrame->copyNonLocationPropertiesFrom($inFrames[$inFramesIndex + 1]);
            }
            $outFrames[] = $outFrame;
        }
        return $outFrames;
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $inFrames
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
     * @param ClassicFormatStackTraceFrame $frame
     *
     * @return bool
     */
    private static function isElasticApmFrameInClassicFormat(ClassicFormatStackTraceFrame $frame): bool
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
     * @param ClassicFormatStackTraceFrame[] $inFrames
     *
     * @return ClassicFormatStackTraceFrame[]
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
     * @param ClassicFormatStackTraceFrame[] $classicFormatFrames
     *
     * @return StackTraceFrame[]
     */
    public static function convertClassicToApmFormat(array $classicFormatFrames): array
    {
        return self::convertPhpToApmFormat(self::convertClassicToPhpFormat($classicFormatFrames));
    }

    /**
     * @param int           $numberOfStackFramesToSkip
     * @param LoggerFactory $loggerFactory
     *
     * @return StackTraceFrame[]
     */
    public static function captureInApmFormat(int $numberOfStackFramesToSkip, LoggerFactory $loggerFactory): array
    {
        return self::convertClassicToApmFormat(
            self::captureInClassicFormat($loggerFactory, /* offset */ $numberOfStackFramesToSkip + 1, /* $maxNumberOfFrames */ null, /* $includeElasticApmFrames */ false)
        );
    }

    /**
     * @param array<string, mixed>[] $debugBacktraceFormatFrames
     * @param LoggerFactory          $loggerFactory
     *
     * @return StackTraceFrame[]
     */
    public static function convertExternallyCapturedToApmFormat(array $debugBacktraceFormatFrames, LoggerFactory $loggerFactory): array
    {
        $phpFormatFrames = self::convertDebugBacktraceToPhpFormat($debugBacktraceFormatFrames, /* includeValuesOnStack */ false, $loggerFactory);
        $classicFormatFrames = self::convertPhpToClassicFormat($phpFormatFrames);
        return self::convertClassicToApmFormat(self::excludeElasticApmInClassicFormat($classicFormatFrames));
    }

    /**
     * @param Throwable     $throwable
     * @param LoggerFactory $loggerFactory
     *
     * @return StackTraceFrame[]
     */
    public static function convertThrowableTraceToApmFormat(Throwable $throwable, LoggerFactory $loggerFactory): array
    {
        $debugBacktraceFormatFrames = $throwable->getTrace();
        $frameForThrowLocation = [StackTraceUtil::FILE_KEY => $throwable->getFile(), StackTraceUtil::LINE_KEY => $throwable->getLine()];
        array_unshift(/* ref */ $debugBacktraceFormatFrames, $frameForThrowLocation);
        return self::convertExternallyCapturedToApmFormat($debugBacktraceFormatFrames, $loggerFactory);
    }
}
