<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\TransactionContextUserInterface;

final class NoopTransactionContextUser implements TransactionContextUserInterface
{
    use NoopObjectTrait;

    /**
     * {@inheritdoc}
     */
    public function setId($id): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setEmail($email): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setUsername($username): void
    {
    }
}
