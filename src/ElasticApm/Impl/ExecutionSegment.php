<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegment implements ExecutionSegmentInterface, LoggableInterface
{
    use LoggableTrait;

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

    /** @var ExecutionSegmentData */
    private $data;

    protected function __construct(
        ExecutionSegmentData $data,
        Tracer $tracer,
        string $traceId,
        string $name,
        string $type,
        ?float $timestamp = null
    ) {
        $systemClockCurrentTime = $tracer->getClock()->getSystemClockCurrentTime();
        $this->data = $data;
        $this->data->timestamp = $timestamp ?? $systemClockCurrentTime;
        $this->durationOnBegin
            = TimeUtil::calcDuration($this->data->timestamp, $systemClockCurrentTime);
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->data->traceId = $traceId;
        $this->data->id = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setName($name);
        $this->setType($type);
        $this->logger = $this->tracer->loggerFactory()
                                     ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                                     ->addContext('this', $this);
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer', 'logger'];
    }

    /**
     * @return bool
     */
    abstract public function isSampled(): bool;

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }
    /**
     * Begins a new span with this execution segment as the new span's parent,
     * runs the provided callback as the new span and automatically ends the new span.
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param Closure     $callback  Callback to execute as the new span
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @param int         $numberOfStackFramesToSkip
     *
     * @return mixed The return value of $callback
     *
     * @template        T
     * @phpstan-param   Closure(SpanInterface $newSpan): T $callback
     * @phpstan-return  T
     *
     * @see             SpanInterface
     *
     * @noinspection    PhpDocMissingThrowsInspection
     */
    protected function captureChildSpanImpl(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype,
        ?string $action,
        ?float $timestamp,
        int $numberOfStackFramesToSkip
    ) {
        $newSpan = $this->beginChildSpan(
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
        try {
            return $callback($newSpan);
        } catch (Throwable $throwable) {
            $newSpan->createError($throwable);
            /** @noinspection PhpUnhandledExceptionInspection */
            throw $throwable;
        } finally {
            // Since endSpanEx was not called directly it should not be kept in the stack trace
            $newSpan->endSpanEx(/* numberOfStackFramesToSkip: */ $numberOfStackFramesToSkip + 1);
        }
    }

    public function beforeMutating(): bool
    {
        if (!$this->isEnded) {
            return false;
        }

        if ($this->isDiscarded) {
            return true;
        }

        ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStacktrace()->log('A mutating method has been called on already ended event');

        return true;
    }

    /** @inheritDoc */
    public function isNoop(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function getId(): string
    {
        return $this->data->id;
    }

    /** @inheritDoc */
    public function getTimestamp(): float
    {
        return $this->data->timestamp;
    }

    /** @inheritDoc */
    public function getTraceId(): string
    {
        return $this->data->traceId;
    }

    /** @inheritDoc */
    public function setName(string $name): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->name = Tracer::limitKeywordString($name);
    }

    /** @inheritDoc */
    public function setType(string $type): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->type = Tracer::limitKeywordString($type);
    }

    /** @inheritDoc */
    public function discard(): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->isDiscarded = true;
        $this->end();
    }

    protected function endExecutionSegment(?float $duration = null): bool
    {
        if ($this->isDiscarded) {
            $this->isEnded = true;
            return false;
        }

        if ($this->beforeMutating()) {
            return false;
        }

        if (is_null($duration)) {
            $monotonicEndTime = $this->tracer->getClock()->getMonotonicClockCurrentTime();
            $calculatedDuration = $this->durationOnBegin
                                  + TimeUtil::calcDuration($this->monotonicBeginTime, $monotonicEndTime);
            if ($calculatedDuration < 0) {
                $calculatedDuration = 0;
            }
            $this->data->duration = $calculatedDuration;
        } else {
            $this->data->duration = $duration;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(ClassNameUtil::fqToShort(get_class($this)) . ' ended', []);

        $this->isEnded = true;
        return true;
    }

    /** @inheritDoc */
    public function hasEnded(): bool
    {
        return $this->isEnded;
    }
}
