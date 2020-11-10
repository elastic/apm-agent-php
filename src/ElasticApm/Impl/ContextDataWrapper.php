<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
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

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $this->toLogLoggableTraitImpl($stream, /* customPropValues */ ['ownerId' => $this->owner->getId()]);
    }
}
