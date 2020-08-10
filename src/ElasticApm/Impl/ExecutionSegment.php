<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegment implements ExecutionSegmentInterface, JsonSerializable
{
    /** @var float */
    protected $duration;

    /** @var float */
    private $durationOnBegin;

    /** @var string */
    protected $id;

    /** @var bool */
    protected $isDiscarded = false;

    /** @var bool */
    private $isEnded = false;

    /** @var Logger */
    private $logger;

    /** @var float Monotonic time since some unspecified starting point, in microseconds */
    private $monotonicBeginTime;

    /** @var string */
    protected $name;

    /** @var float UTC based and in microseconds since Unix epoch */
    protected $timestamp;

    /** @var string */
    protected $traceId;

    /** @var Tracer */
    protected $tracer;

    /** @var string */
    protected $type;

    protected function __construct(
        Tracer $tracer,
        string $traceId,
        string $name,
        string $type,
        ?float $timestamp = null
    ) {
        $systemClockCurrentTime = $tracer->getClock()->getSystemClockCurrentTime();
        $this->timestamp = $timestamp ?? $systemClockCurrentTime;
        $this->durationOnBegin = TimeUtil::calcDuration($this->timestamp, $systemClockCurrentTime);
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->traceId = $traceId;
        $this->id = IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setName($name);
        $this->setType($type);
        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function checkIfAlreadyEnded(string $calledMethodName): bool
    {
        if (!$this->isEnded) {
            return false;
        }

        if ($this->isDiscarded) {
            return true;
        }

        ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            $calledMethodName . '() has been called on already ended ' . DbgUtil::fqToShortClassName(get_class())
            // ['stackTrace' => DbgUtil::formatCurrentStackTrace(/* numberOfStackFramesToSkip */ 1)]
        );

        return true;
    }

    public function createLogger(string $namespace, string $className, string $srcCodeFile): Logger
    {
        $logger = $this->tracer
            ->loggerFactory()->loggerForClass(LogCategory::PUBLIC_API, $namespace, $className, $srcCodeFile);
        $logger->addContext('Id', $this->getId());
        $logger->addContext('TraceId', $this->getId());
        return $logger;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }
    public function setName(string $name): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->name = TextUtil::limitKeywordString($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setType(string $type): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->type = TextUtil::limitKeywordString($type);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        $newSpan = $this->beginChildSpan($name, $type, $subtype, $action, $timestamp);
        try {
            return $callback($newSpan);
        } finally {
            $newSpan->end();
        }
    }

    public function discard(): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->isDiscarded = true;
        $this->isEnded = true;
    }

    public function end(?float $duration = null): void
    {
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
        && $loggerProxy->log(DbgUtil::fqToShortClassName(get_class()) . ' ended');

        $this->isEnded = true;
    }

    public function hasEnded(): bool
    {
        return $this->isEnded;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResult(
            [
                'duration'  => $this->duration,
                'id'        => $this->id,
                'name'      => $this->name,
                'timestamp' => PHP_INT_SIZE >= 8 ? intval($this->timestamp) : $this->timestamp,
                'trace_id'  => $this->traceId,
                'type'      => $this->type,
            ]
        );
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class()));
        $builder->add('ID', $this->id);
        $builder->add('name', $this->name);
        return $builder->build();
    }
}
