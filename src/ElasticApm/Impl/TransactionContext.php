<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\TransactionContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContext extends ExecutionSegmentContext implements TransactionContextInterface
{
    use LoggableTrait;

    /** @var TransactionContextData */
    private $data;

    public function __construct(Transaction $owner, TransactionContextData $data)
    {
        parent::__construct($owner, $data);
        $this->data = $data;
    }
}
