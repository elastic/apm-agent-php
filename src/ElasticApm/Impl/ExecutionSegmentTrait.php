<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait ExecutionSegmentTrait
{
    /** @var Tracer */
    protected $tracer;

    /** @var bool */
    protected $isDiscarded = false;

    /** @var float */
    private $durationOnBegin;

    /** @var float Monotonic time since some unspecified starting point, in microseconds */
    private $monotonicBeginTime;

    /** @var Logger */
    private $logger;

    /** @var bool */
    private $isEnded = false;

    protected function constructExecutionSegmentTrait(
        Tracer $tracer,
        string $traceId,
        string $name,
        string $type,
        ?float $timestamp = null
    ): void {
        $systemClockCurrentTime = $this->timestamp = $tracer->getClock()->getSystemClockCurrentTime();
        if ($timestamp === null) {
            $this->timestamp = $systemClockCurrentTime;
        } else {
            $this->timestamp = $timestamp;
        }
        $this->durationOnBegin = TimeUtil::calcDuration($this->timestamp, $systemClockCurrentTime);
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->traceId = $traceId;
        $this->id = IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setName($name);
        $this->setType($type);
        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);
    }

    protected function createLogger(string $namespace, string $className, string $srcCodeFile): Logger
    {
        $logger = $this->tracer
            ->loggerFactory()->loggerForClass(LogCategory::PUBLIC_API, $namespace, $className, $srcCodeFile);
        $logger->addContext('Id', $this->getId());
        $logger->addContext('TraceId', $this->getId());
        return $logger;
    }

    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        /** @var SpanInterface $newSpan */
        $newSpan = $this->beginChildSpan(
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
        try {
            return $callback($newSpan);
        } finally {
            // Since endSpanEx was not called directly it should not be kept in the stack trace
            $newSpan->endSpanEx(/* numberOfStackFramesToSkip: */ 1);
        }
    }

    protected function endExecutionSegment(?float $duration = null): bool
    {
        if ($this->isDiscarded) {
            $this->isEnded = true;
            return false;
        }

        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return false;
        }

        if ($duration === null) {
            $monotonicEndTime = $this->tracer->getClock()->getMonotonicClockCurrentTime();
            $this->duration = $this->durationOnBegin
                              + TimeUtil::calcDuration($this->monotonicBeginTime, $monotonicEndTime);
            if ($this->duration < 0) {
                $this->duration = 0;
            }
        } else {
            $this->duration = $duration;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(DbgUtil::fqToShortClassName(get_class($this)) . ' ended', []);

        $this->isEnded = true;
        return true;
    }

    public function hasEnded(): bool
    {
        return $this->isEnded;
    }

    protected function checkIfAlreadyEnded(string $calledMethodName): bool
    {
        if (!$this->isEnded) {
            return false;
        }

        if ($this->isDiscarded) {
            return true;
        }

        ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            $calledMethodName . '() has been called on already ended ' . DbgUtil::fqToShortClassName(get_class($this)),
            ['stackTrace' => DbgUtil::formatCurrentStackTrace(/* numberOfStackFramesToSkip */ 1)]
        );

        return true;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    public function setName(string $name): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->name = Tracer::limitKeywordString($name);
    }

    public function setType(string $type): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->type = Tracer::limitKeywordString($type);
    }

    public function setLabel(string $key, $value): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        if (!ExecutionSegmentData::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        $this->labels[Tracer::limitKeywordString($key)] = is_string($value)
            ? Tracer::limitKeywordString($value)
            : $value;
    }

    protected function discardExecutionSegment(): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->isDiscarded = true;
        $this->end();
    }

    public function discard(): void
    {
        $this->discardExecutionSegment();
    }
}
