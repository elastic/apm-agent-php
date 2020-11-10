<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ContextDataWrapper implements LoggableInterface
{
    use LoggableTrait;

    /** @var ExecutionSegment */
    private $owner;

    protected function __construct(ExecutionSegment $owner)
    {
        $this->owner = $owner;
    }

    protected function beforeMutating(): bool
    {
        return $this->owner->beforeMutating();
    }

    protected function getTracer(): Tracer
    {
        return $this->owner->getTracer();
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['owner'];
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $this->toLogLoggableTraitImpl($stream, ['ownerId' => $this->owner->getId()]);
    }
}
