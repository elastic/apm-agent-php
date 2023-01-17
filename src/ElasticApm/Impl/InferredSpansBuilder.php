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

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\StackTraceUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InferredSpansBuilder implements LoggableInterface
{
    use LoggableTrait;

    /** @var float In milliseconds */
    private $minDurationInMilliseconds;

    /** @var InferredSpanFrame[] */
    private $openFramesReverseOrder = [];

    /** @var Tracer */
    private $tracer;

    /** @var Transaction */
    private $transaction;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(
            $tracer->getCurrentTransaction() instanceof Transaction
            && $tracer->getCurrentTransaction()->isSampled()
            && $tracer->getCurrentExecutionSegment() === $tracer->getCurrentTransaction()
        )
        && $assertProxy->withContext(
            '$tracer->getCurrentTransaction() instanceof Transaction
             && $tracer->getCurrentTransaction()->isSampled()
             && $tracer->getCurrentExecutionSegment() === $tracer->getCurrentTransaction()',
            ['$tracer->getCurrentTransaction()' => $tracer->getCurrentTransaction()]
        );

        $this->tracer = $tracer;
        /**
         * We asserted that $tracer->getCurrentTransaction() is instance of Transaction above
         *
         * @phpstan-ignore-next-line
         */
        $this->transaction = $tracer->getCurrentTransaction();
        $this->minDurationInMilliseconds = $tracer->getConfig()->profilingInferredSpansMinDurationInMilliseconds();

        $this->logger = $tracer->loggerFactory()
            ->loggerForClass(LogCategory::INFERRED_SPANS, __NAMESPACE__, __CLASS__, __FILE__)
            ->addContext('this', $this);
    }

    /**
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function captureStackTrace(int $offset, LoggerFactory $loggerFactory): array
    {
        return StackTraceUtil::captureInClassicFormatExcludeElasticApm(
            $loggerFactory,
            $offset + 1,
            DEBUG_BACKTRACE_IGNORE_ARGS /* <- options */
        );
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $newStackTrace
     * @param int                            $bottomNotExtendedFrameIndex
     * @param ?ClassicFormatStackTraceFrame  $frameCopy
     * @param ?int                           $frameCopyIndex
     *
     * @return void
     */
    private function extendOpenFrames(
        array $newStackTrace,
        int &$bottomNotExtendedFrameIndex,
        ?ClassicFormatStackTraceFrame &$frameCopy,
        ?int &$frameCopyIndex
    ): void {
        $openFramesCount = count($this->openFramesReverseOrder);
        $newStackTraceCount = count($newStackTrace);
        /** @var ?ClassicFormatStackTraceFrame $newStackTraceParentFrame */
        $newStackTraceParentFrame = null;
        /** @var ?InferredSpanFrame $openParentFrame */
        $openParentFrame = null;
        for ($i = 0; $i != $openFramesCount && $i != $newStackTraceCount; ++$i) {
            // If we are inside the same frame but at the different line we should not extend child frame
            if (
                $newStackTraceParentFrame !== null
                && $openParentFrame !== null
                && $openParentFrame->stackFrame->line !== $newStackTraceParentFrame->line
            ) {
                // We need to capture stack trace we update line below
                $frameCopy = clone $openParentFrame->stackFrame;
                $frameCopyIndex = $i - 1;
                $openParentFrame->stackFrame->line = $newStackTraceParentFrame->line;
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Cannot be extended because parent frames lines do not match',
                    [
                        'newStackTraceParentFrame' => $newStackTraceParentFrame,
                        'openParentFrame'          => $openParentFrame,
                        'i'                        => $i,
                        'newStackTrace'            => $newStackTrace,
                    ]
                );
                break;
            }
            $openFrame = $this->openFramesReverseOrder[$i];
            $newFrame = $newStackTrace[$newStackTraceCount - $i - 1];
            if (!$openFrame->canBeExtendedWith($newFrame)) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Cannot be extended because frames do not match',
                    ['newStackTrace' => $newStackTrace, 'i' => $i]
                );
                break;
            }

            $openParentFrame = $openFrame;
            $newStackTraceParentFrame = $newFrame;
        }

        $bottomNotExtendedFrameIndex = $i;
    }

    /**
     * @param int                           $forFrameIndex
     * @param ?ClassicFormatStackTraceFrame $frameCopy
     * @param ?int                          $frameCopyIndex
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    private function buildStackTrace(
        int $forFrameIndex,
        ?ClassicFormatStackTraceFrame $frameCopy,
        ?int $frameCopyIndex
    ): array {
        $openFramesCount = count($this->openFramesReverseOrder);
        $result = [];
        for ($i = $forFrameIndex; $i >= 0 && $i < $openFramesCount; --$i) {
            $useFrameCopy = $frameCopy !== null && $frameCopyIndex === $i; // @phpstan-ignore-line
            $result[] = $useFrameCopy ? $frameCopy : $this->openFramesReverseOrder[$i]->stackFrame;
        }
        return $result;
    }

    /**
     * @param int                           $openFrameIndex
     * @param string                        $parentId
     * @param ?ClassicFormatStackTraceFrame $frameCopy
     * @param ?int                          $frameCopyIndex
     *
     * @return void
     */
    private function sendFrameAsSpan(
        int $openFrameIndex,
        string $parentId,
        ?ClassicFormatStackTraceFrame $frameCopy,
        ?int $frameCopyIndex
    ): void {
        $frame = $this->openFramesReverseOrder[$openFrameIndex];
        $stackTraceClassic = $this->buildStackTrace($openFrameIndex, $frameCopy, $frameCopyIndex);
        $stackTrace = StackTraceUtil::convertClassicToApmFormat($stackTraceClassic);
        $frame->prepareForSerialization($this->transaction, $parentId, $stackTrace);
        $this->tracer->sendSpanToApmServer($frame);
    }

    private function findParentId(int $childFrameIndex, int $bottomNotExtendedIndex): string
    {
        $loggerTrace = $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__);

        if ($bottomNotExtendedIndex === 0) {
            $loggerTrace && $loggerTrace->log(
                __LINE__,
                'Using current execution segment ID as parent ID'
                . ' because there are no open frames below the one to be sent',
                ['current execution segment ID' => $this->tracer->getCurrentExecutionSegment()->getId()]
            );
            return $this->tracer->getCurrentExecutionSegment()->getId();
        }

        $parentFrame = $this->openFramesReverseOrder[$bottomNotExtendedIndex - 1];
        if ($parentFrame->isAllocatedToBeSent() || $this->transaction->tryToAllocateStartedSpan()) {
            $parentId = $parentFrame->markAsAllocatedToBeSent();
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                "Using parent frame",
                [
                    'parentFrame'            => $parentFrame,
                    'childFrameIndex'        => $childFrameIndex,
                    'bottomNotExtendedIndex' => $bottomNotExtendedIndex,
                ]
            );
            return $parentId;
        }

        $loggerTrace && $loggerTrace->log(
            __LINE__,
            'Using current execution segment ID as parent ID'
            . ' because the current transaction reached configured started spans limit',
            ['current execution segment ID' => $this->tracer->getCurrentExecutionSegment()->getId()]
        );
        return $this->tracer->getCurrentExecutionSegment()->getId();
    }

    private function tryToAllocateToBeSent(InferredSpanFrame $frame, ?int $toBeSentDescendantIndex): bool
    {
        $loggerTrace = $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__);

        if ($frame->duration < $this->minDurationInMilliseconds) {
            $loggerTrace && $loggerTrace->log(
                __LINE__,
                "Frame will NOT be sent as inferred span because its duration is below configured minimum",
                [
                    'frame' => $frame,
                    OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION . ' (milliseconds)'
                            => $this->minDurationInMilliseconds,
                ]
            );
            return false;
        }

        if ($toBeSentDescendantIndex !== null) {
            $toBeSentDescendant = $this->openFramesReverseOrder[$toBeSentDescendantIndex];
            if ($frame->duration <= $toBeSentDescendant->duration) {
                $loggerTrace && $loggerTrace->log(
                    __LINE__,
                    "Frame will NOT be sent as inferred span"
                    . " because it's duration is not greater than nearest sent descendant",
                    ['frame' => $frame, 'toBeSentDescendant' => $toBeSentDescendant]
                );
                return false;
            }
        }

        if (!$this->transaction->tryToAllocateStartedSpan()) {
            $loggerTrace && $loggerTrace->log(
                __LINE__,
                "Frame will NOT be sent as inferred span"
                . " because the current transaction reached configured started spans limit",
                ['frame' => $frame]
            );
            return false;
        }

        $loggerTrace && $loggerTrace->log(
            __LINE__,
            "Frame will be sent as inferred span because it satisfies all the requirements",
            ['frame' => $frame]
        );
        $frame->markAsAllocatedToBeSent();
        return true;
    }

    /**
     * @param int                           $bottomNotExtendedFrameIndex
     * @param ?ClassicFormatStackTraceFrame $frameCopy
     * @param ?int                          $frameCopyIndex
     * @param float                         $systemClockNow
     * @param float                         $monotonicClockNow
     *
     * @return void
     */
    private function processNotExtendedOpenFrames(
        int $bottomNotExtendedFrameIndex,
        ?ClassicFormatStackTraceFrame $frameCopy,
        ?int $frameCopyIndex,
        float $systemClockNow,
        float $monotonicClockNow
    ): void {
        $openFramesCount = count($this->openFramesReverseOrder);
        /** @var ?int $toBeSentDescendantIndex */
        $toBeSentDescendantIndex = null;
        for ($i = $openFramesCount - 1; $i >= $bottomNotExtendedFrameIndex; --$i) {
            $frame = $this->openFramesReverseOrder[$i];
            $frame->setEndTime($systemClockNow, $monotonicClockNow, $this->tracer->loggerFactory());
            if ($frame->isAllocatedToBeSent() || $this->tryToAllocateToBeSent($frame, $toBeSentDescendantIndex)) {
                if ($toBeSentDescendantIndex !== null) {
                    /** @var string $parentId */
                    $parentId = $frame->id;
                    $this->sendFrameAsSpan($toBeSentDescendantIndex, $parentId, $frameCopy, $frameCopyIndex);
                }
                $toBeSentDescendantIndex = $i;
            }
        }
        if ($toBeSentDescendantIndex !== null) {
            $parentId = $this->findParentId($toBeSentDescendantIndex, $bottomNotExtendedFrameIndex);
            $this->sendFrameAsSpan($toBeSentDescendantIndex, $parentId, $frameCopy, $frameCopyIndex);
        }

        $this->openFramesReverseOrder = array_slice($this->openFramesReverseOrder, 0, $bottomNotExtendedFrameIndex);
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $newStackTrace
     */
    public function addStackTrace(array $newStackTrace): void
    {
        $monotonicClockNow = $this->tracer->getClock()->getMonotonicClockCurrentTime();
        $systemClockNow = $this->tracer->getClock()->getSystemClockCurrentTime();

        ($loggerTrace = $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__))
        && $loggerTrace->log(
            __LINE__,
            'Entered',
            [
                'newStackTrace'     => $newStackTrace,
                'systemClockNow'    => $systemClockNow,
                'monotonicClockNow' => $monotonicClockNow,
            ]
        );

        $openFramesCount = count($this->openFramesReverseOrder);
        $bottomNotExtendedFrameIndex = $openFramesCount;
        /** @var ?ClassicFormatStackTraceFrame $frameCopy */
        $frameCopy = null;
        /** @var ?int $frameCopyIndex */
        $frameCopyIndex = null;
        $this->extendOpenFrames(
            $newStackTrace,
            $bottomNotExtendedFrameIndex /* <- out */,
            $frameCopy /* <- out */,
            $frameCopyIndex /* <- out */
        );

        if ($bottomNotExtendedFrameIndex === $openFramesCount) {
            $loggerTrace && $loggerTrace->log(__LINE__, 'All open frames were extended');
        } else {
            $loggerTrace && $loggerTrace->log(
                __LINE__,
                'Not all open frames were extended - some will become inferred spans',
                ['not extended frames' => array_slice($this->openFramesReverseOrder, $bottomNotExtendedFrameIndex)]
            );

            $this->processNotExtendedOpenFrames(
                $bottomNotExtendedFrameIndex,
                $frameCopy,
                $frameCopyIndex,
                $systemClockNow,
                $monotonicClockNow
            );
        }

        $newStackTraceCount = count($newStackTrace);
        for ($i = $bottomNotExtendedFrameIndex; $i != $newStackTraceCount; ++$i) {
            $newFrame = $newStackTrace[$newStackTraceCount - $i - 1];
            $this->openFramesReverseOrder[]
                = new InferredSpanFrame($systemClockNow, $monotonicClockNow, $newFrame);
            $loggerTrace && $loggerTrace->log(__LINE__, 'Appended new frame on top of open frames');
        }
    }

    public function close(): void
    {
        $this->addStackTrace([]);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(self::defaultPropertiesExcludedFromLog(), ['tracer', 'transaction']);
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $transactionId = $this->transaction === null ? null : $this->transaction->getId();
        $this->toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            ['transactionId' => $transactionId]
        );
    }
}
