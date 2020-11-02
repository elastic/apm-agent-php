<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\StacktraceFrame;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends SpanData implements SpanInterface
{
    use ExecutionSegmentTrait;

    /** @var Logger */
    private $logger;

    /** @var Transaction */
    private $containingTransaction;

    /** @var Span|null */
    private $parentSpan;

    /** @var bool */
    private $isDropped;

    public function __construct(
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype,
        ?string $action,
        ?float $timestamp,
        bool $isDropped
    ) {
        $this->constructExecutionSegmentTrait(
            $containingTransaction->getTracer(),
            $containingTransaction->getTraceId(),
            $name,
            $type,
            $timestamp
        );

        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->transactionId = $containingTransaction->getId();

        $this->parentSpan = $parentSpan;
        $this->parentId = $parentSpan === null ? $containingTransaction->getId() : $parentSpan->getId();

        $this->start = TimeUtil::calcDuration($containingTransaction->getTimestamp(), $this->getTimestamp());

        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        $this->isDropped = $isDropped;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Span created', ['parentId' => $this->parentId]);
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return $this->containingTransaction->beginSpan(
            $this /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        ) ?? NoopSpan::singletonInstance();
    }

    public function isSampled(): bool
    {
        return $this->containingTransaction->isSampled();
    }

    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        // This method is part of public API so it should be kept in the stack trace
        // if $numberOfStackFramesToSkip is 0
        $this->stacktrace = self::captureStacktrace($numberOfStackFramesToSkip);

        if ($this->shouldBeSentToApmServer()) {
            $this->containingTransaction->queueSpanToSend($this);
        }

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->setCurrentSpan($this->parentSpan);
        }
    }

    public function end(?float $duration = null): void
    {
        // Since endSpanEx was not called directly it should not be kept in the stack trace
        $this->endSpanEx(/* numberOfStackFramesToSkip: */ 1, $duration);
    }

    public function setAction(?string $action): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->action = $this->tracer->limitNullableKeywordString($action);
    }

    public function setSubtype(?string $subtype): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->subtype = $this->tracer->limitNullableKeywordString($subtype);
    }

    public function containingTransaction(): Transaction
    {
        return $this->containingTransaction;
    }

    public function parentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    /**
     * @param int $numberOfStackFramesToSkip
     *
     * @return StacktraceFrame[]
     */
    private static function captureStacktrace(int $numberOfStackFramesToSkip): array
    {
        $srcFrames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        /** @var StacktraceFrame[] */
        $dstFrames = [];
        for ($i = $numberOfStackFramesToSkip + 1; $i < count($srcFrames); ++$i) {
            $srcFrame = $srcFrames[$i];

            $dstFrame = new StacktraceFrame(
                ArrayUtil::getValueIfKeyExistsElse('file', $srcFrame, 'FILE NAME N/A'),
                ArrayUtil::getValueIfKeyExistsElse('line', $srcFrame, 0)
            );

            $className = ArrayUtil::getValueIfKeyExistsElse('class', $srcFrame, null);
            if (!is_null($className)) {
                if ($className === Span::class) {
                    $className = SpanInterface::class;
                } elseif ($className === Transaction::class) {
                    $className = TransactionInterface::class;
                }
            }
            $funcName = ArrayUtil::getValueIfKeyExistsElse('function', $srcFrame, null);
            $callType = ArrayUtil::getValueIfKeyExistsElse('type', $srcFrame, '.');
            $dstFrame->function = is_null($className)
                ? is_null($funcName) ? null : ($funcName . '()')
                : (($className . $callType) . (is_null($funcName) ? 'FUNCTION NAME N/A' : ($funcName . '()')));

            $dstFrames[] = $dstFrame;
        }

        return $dstFrames;
    }

    private function shouldBeSentToApmServer(): bool
    {
        return $this->containingTransaction->isSampled() && (!$this->isDropped);
    }

    public function __toString(): string
    {
        return $this->toStringExcludeProperties(['containingTransaction', 'parentSpan', 'logger', 'stacktrace']);
    }
}
