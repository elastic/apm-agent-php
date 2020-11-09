<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegment implements
    ExecutionSegmentInterface,
    ExecutionSegmentContextInterface,
    LoggableInterface
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

    protected function __construct(
        Tracer $tracer,
        string $traceId,
        string $name,
        string $type,
        ?float $timestamp = null
    ) {
        $systemClockCurrentTime = $tracer->getClock()->getSystemClockCurrentTime();
        $this->executionSegmentData()->timestamp = $timestamp ?? $systemClockCurrentTime;
        $this->durationOnBegin
            = TimeUtil::calcDuration($this->executionSegmentData()->timestamp, $systemClockCurrentTime);
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->executionSegmentData()->traceId = $traceId;
        $this->executionSegmentData()->id = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setName($name);
        $this->setType($type);
        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer', 'logger'];
    }

    /**
     * @return ExecutionSegmentData
     */
    abstract protected function executionSegmentData(): ExecutionSegmentData;

    /**
     * @return ExecutionSegmentContextData
     */
    abstract protected function executionSegmentContextData(): ExecutionSegmentContextData;

    /**
     * @return bool
     */
    abstract public function isSampled(): bool;

    protected function createLogger(string $namespace, string $className, string $srcCodeFile): Logger
    {
        $logger = $this->tracer->loggerFactory()
                               ->loggerForClass(LogCategory::PUBLIC_API, $namespace, $className, $srcCodeFile);
        $logger->addContext('this', $this);
        return $logger;
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

    /**
     * @return bool
     */
    protected function isContextEmpty(): bool
    {
        return empty($this->executionSegmentContextData()->labels);
    }

    protected function beforeMutating(): bool
    {
        if (!$this->isEnded) {
            return false;
        }

        if ($this->isDiscarded) {
            return true;
        }

        ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
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
        return $this->executionSegmentData()->id;
    }

    /** @inheritDoc */
    public function getTimestamp(): float
    {
        return $this->executionSegmentData()->timestamp;
    }

    /** @inheritDoc */
    public function getTraceId(): string
    {
        return $this->executionSegmentData()->traceId;
    }

    /** @inheritDoc */
    public function setName(string $name): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->executionSegmentData()->name = Tracer::limitKeywordString($name);
    }

    /** @inheritDoc */
    public function setType(string $type): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->executionSegmentData()->type = Tracer::limitKeywordString($type);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function doesValueHaveSupportedLabelType($value): bool
    {
        return is_null($value) || is_string($value) || is_bool($value) || is_int($value) || is_float($value);
    }

    /** @inheritDoc */
    public function setLabel(string $key, $value): void
    {
        if ($this->beforeMutating() || (!$this->isSampled())) {
            return;
        }

        if (!self::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        $this->executionSegmentContextData()->labels[Tracer::limitKeywordString($key)] = is_string($value)
            ? Tracer::limitKeywordString($value)
            : $value;
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
            $this->executionSegmentData()->duration = $calculatedDuration;
        } else {
            $this->executionSegmentData()->duration = $duration;
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
