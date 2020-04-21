<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopSpan extends NoopExecutionSegment implements SpanInterface
{
    use NoopObjectTrait;

    /** @var string */
    public const NAME = 'NO-OP span';

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return NoopTransaction::ID;
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return NoopTransaction::ID;
    }

    /** @inheritDoc */
    public function getStart(): ?float
    {
        return 0.0;
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return self::NAME;
    }

    /** @inheritDoc */
    public function setName(string $name): void
    {
    }

    /** @inheritDoc */
    public function getSubtype(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
    }

    /** @inheritDoc */
    public function getAction(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
    }
}
