<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Impl\Util\NoopObjectTrait;

class NoopSpan extends NoopExecutionSegment implements SpanInterface
{
    use NoopObjectTrait;

    /** @var string */
    public const NAME = 'NO-OP span';

    /**
     * Constructor is hidden because create() should be used instead.
     */
    private function __construct()
    {
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return NoopTransaction::ID;
    }

    public function setTransactionId(string $transactionId): void
    {
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return NoopTransaction::ID;
    }

    /** @inheritDoc */
    public function setParentId(string $parentId): void
    {
    }

    /** @inheritDoc */
    public function getStart(): int
    {
        return 0;
    }

    /** @inheritDoc */
    public function setStart(?int $start): void
    {
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
