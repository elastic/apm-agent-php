<?php

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\SpanInterface;

final class SpanInfo implements LoggableInterface
{
    use LoggableTrait;

    /** @var SpanInterface */
    public $span;

    /** @var string */
    public $name;

    /** @var int */
    public $childCount = 0;

    /** @var bool */
    public $needsExplicitEndCall;

    public function __construct(SpanInterface $span, string $name, bool $needsExplicitEndCall)
    {
        $this->span = $span;
        $this->name = $name;
        $this->needsExplicitEndCall = $needsExplicitEndCall;
    }
}
