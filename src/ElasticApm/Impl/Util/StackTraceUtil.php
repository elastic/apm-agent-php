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
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

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
     * @param LoggerFactory $loggerFactory
     * @param int           $offset
     * @param int           $options
     * @param int           $limit
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function captureInClassicFormatExcludeElasticApm(
        LoggerFactory $loggerFactory,
        int $offset = 0,
        int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT,
        int $limit = 0
    ): array {
        return self::captureInClassicFormat(
            $loggerFactory,
            $offset + 1,
            $options,
            $limit,
            false /* <- includeElasticApmFrames */
        );
    }

    /**
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function captureInClassicFormat(
        ?LoggerFactory $loggerFactory,
        int $offset = 0,
        int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT,
        int $limit = 0,
        bool $includeElasticApmFrames = true
    ): array {
        $phpFrames = self::captureInPhpFormat($loggerFactory, $offset + 1, $options, $limit === 0 ? 0 : ($limit + 1));
        $classicFrames = self::convertPhpToClassicFormatOmitTopFrame($phpFrames);
        $classicFrames = $limit === 0 ? $classicFrames : array_slice($classicFrames, /* offset */ 0, $limit);

        return $includeElasticApmFrames ? $classicFrames : self::excludeElasticApmInClassicFormat($classicFrames);
    }

    /**
     * @return PhpFormatStackTraceFrame[]
     */
    public static function captureInPhpFormat(
        ?LoggerFactory $loggerFactory,
        int $offset = 0,
        int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT,
        int $limit = 0
    ): array {
        $srcFrames = array_slice(debug_backtrace($options, $limit === 0 ? 0 : ($offset + $limit)), $offset);
        if (count($srcFrames) === 0) {
            return [];
        }

        // It seems that sometimes the bottom frame include args even when DEBUG_BACKTRACE_IGNORE_ARGS is set
        if (
            (($options & DEBUG_BACKTRACE_IGNORE_ARGS) !== 0)
            && (($srcFramesCount = count($srcFrames)) !== 0)
            && array_key_exists(self::ARGS_KEY, ($bottomFrameRef = &$srcFrames[$srcFramesCount - 1]))
        ) {
            unset($bottomFrameRef[self::ARGS_KEY]);
            unset($bottomFrameRef);
        }

        /** @var PhpFormatStackTraceFrame[] $result */
        $result = [];
        foreach ($srcFrames as $srcFrame) {
            $newFrame = new PhpFormatStackTraceFrame();
            $newFrame->copyDataFromFromDebugBacktraceFrame($srcFrame, $loggerFactory);
            $result[] = $newFrame;
        }
        $result[0]->resetNonLocationProperties();

        return $result;
    }

    /**
     * @template TStackFrameInputFormat of StackTraceFrameBase
     * @template TStackFrameOutputFormat of StackTraceFrameBase
     *
     * @param TStackFrameInputFormat[]              $inFormatFrames
     * @param class-string<TStackFrameOutputFormat> $outFormatClass
     * @param bool                                  $shiftAwayFromTop
     *
     * @return TStackFrameOutputFormat[]
     */
    private static function shiftLocationData(
        array $inFormatFrames,
        string $outFormatClass,
        bool $shiftAwayFromTop
    ): array {
        $inFormatFramesCount = count($inFormatFrames);
        if ($inFormatFramesCount === 0) {
            return [];
        }
        $outFormatFrames = [];
        foreach (RangeUtil::generateUpTo($inFormatFramesCount) as $frameIndex) {
            /** @var ?TStackFrameInputFormat $inFormatFrameWithNonLocationData */
            $inFormatFrameWithNonLocationData = null;
            if ($shiftAwayFromTop) {
                if ($frameIndex + 1 < $inFormatFramesCount) {
                    $inFormatFrameWithNonLocationData = $inFormatFrames[$frameIndex + 1];
                }
            } else {
                if ($frameIndex > 0) {
                    $inFormatFrameWithNonLocationData = $inFormatFrames[$frameIndex - 1];
                }
            }
            $inFormatFrameCurrent = $inFormatFrames[$frameIndex];
            $outFormatFrame = new $outFormatClass();

            $outFormatFrame->copyLocationPropertiesFrom($inFormatFrameCurrent);

            if ($inFormatFrameWithNonLocationData !== null) {
                $outFormatFrame->copyNonLocationPropertiesFrom($inFormatFrameWithNonLocationData);
            }
            $outFormatFrames[] = $outFormatFrame;
        }
        return $outFormatFrames;
    }

    /**
     * @param PhpFormatStackTraceFrame[] $phpFormatFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function convertPhpToClassicFormatOmitTopFrame(array $phpFormatFrames): array
    {
        return self::shiftLocationData(
            $phpFormatFrames,
            ClassicFormatStackTraceFrame::class,
            /* shiftAwayFromTop */ true
        );
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $classicFormatFrames
     *
     * @return PhpFormatStackTraceFrame[]
     */
    public static function convertClassicToPhpFormat(array $classicFormatFrames): array
    {
        return self::shiftLocationData(
            $classicFormatFrames,
            PhpFormatStackTraceFrame::class,
            /* shiftAwayFromTop */ false
        );
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
            return TextUtil::isPrefixOf('Elastic\\Apm\\', $frame->class)
                   || TextUtil::isPrefixOf('\\Elastic\\Apm\\', $frame->class);
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

    public static function convertClassAndMethodToFunctionName(
        ?string $classicName,
        ?bool $isStaticMethod,
        ?string $methodName
    ): ?string {
        if ($methodName === null) {
            return null;
        }

        if ($classicName === null) {
            return $methodName;
        }

        $classMethodSep = ($isStaticMethod === null)
            ? '.'
            : ($isStaticMethod ? self::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE : self::FUNCTION_IS_METHOD_TYPE_VALUE);
        return $classicName . $classMethodSep . $methodName;
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $classicFormatFrames
     *
     * @return StackTraceFrame[]
     */
    public static function convertClassicToApmFormat(array $classicFormatFrames): array
    {
        $result = [];
        foreach ($classicFormatFrames as $classicFormatFrame) {
            if ($classicFormatFrame->file === null && $classicFormatFrame->line === null) {
                continue;
            }
            $newFrame = new StackTraceFrame(
                $classicFormatFrame->file ?? self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE,
                $classicFormatFrame->line ?? self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE
            );
            $newFrame->function = self::convertClassAndMethodToFunctionName(
                $classicFormatFrame->class,
                $classicFormatFrame->isStaticMethod,
                $classicFormatFrame->function
            );
            $result[] = $newFrame;
        }
        return $result;
    }

    /**
     * @param int  $numberOfStackFramesToSkip
     * @param bool $hideElasticApmImpl
     *
     * @return StackTraceFrame[]
     */
    public static function captureCurrent(int $numberOfStackFramesToSkip, bool $hideElasticApmImpl): array
    {
        return self::convertFromPhp(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            $numberOfStackFramesToSkip + 1,
            $hideElasticApmImpl
        );
    }

    /**
     * @param array<string, mixed>[] $srcFrames
     * @param int                    $numberOfStackFramesToSkip
     * @param bool                   $hideElasticApmImpl
     *
     * @return StackTraceFrame[]
     */
    public static function convertFromPhp(
        array $srcFrames,
        int $numberOfStackFramesToSkip = 0,
        bool $hideElasticApmImpl = false
    ): array {
        /** @var StackTraceFrame[] $dstFrames */
        $dstFrames = [];
        for ($i = $numberOfStackFramesToSkip; $i < count($srcFrames); ++$i) {
            $srcFrame = $srcFrames[$i];

            $dstFrame = new StackTraceFrame(
                ArrayUtil::getStringValueIfKeyExistsElse(
                    StackTraceUtil::FILE_KEY,
                    $srcFrame,
                    self::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE
                ),
                ArrayUtil::getIntValueIfKeyExistsElse(
                    StackTraceUtil::LINE_KEY,
                    $srcFrame,
                    self::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE
                )
            );

            $className = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::CLASS_KEY, $srcFrame, null);
            if ($hideElasticApmImpl && $className !== null) {
                if ($className === Span::class) {
                    $className = SpanInterface::class;
                } elseif ($className === Transaction::class) {
                    $className = TransactionInterface::class;
                }
            }
            $funcName = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::FUNCTION_KEY, $srcFrame, null);
            $callType = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::TYPE_KEY, $srcFrame, '.');
            $dstFrame->function = $className === null
                ? $funcName === null ? null : ($funcName . '()')
                : (($className . $callType) . ($funcName === null ? 'FUNCTION NAME N/A' : ($funcName . '()')));

            $dstFrames[] = $dstFrame;
        }

        return $dstFrames;
    }
}
