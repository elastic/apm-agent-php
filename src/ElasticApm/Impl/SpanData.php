<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\StacktraceFrame;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SpanData extends ExecutionSegmentData implements SpanDataInterface
{
    /** @var string|null */
    protected $action = null;

    /** @var string */
    protected $parentId;

    /** @var StacktraceFrame[]|null */
    protected $stacktrace = null;

    /** @var float */
    protected $start;

    /** @var string|null */
    protected $subtype = null;

    /** @var string */
    protected $transactionId;

    /** @inheritDoc */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->parentId;
    }

    /** @inheritDoc */
    public function getStacktrace(): ?array
    {
        return $this->stacktrace;
    }

    /** @inheritDoc */
    public function getStart(): float
    {
        return $this->start;
    }

    /** @inheritDoc */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @param string               $propKey
     * @param mixed                $propValue
     * @param array<string, mixed> $result
     */
    protected function serializeProperty(string $propKey, $propValue, array &$result): void
    {
        // Don't serialize properties added by a derived class
        if (!property_exists(__CLASS__, $propKey)) {
            return;
        }

        parent::serializeProperty($propKey, $propValue, /* ref */ $result);
    }
}
