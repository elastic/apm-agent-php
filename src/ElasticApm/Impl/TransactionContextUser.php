<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\TransactionContextUserInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends         ContextDataWrapper<Transaction>
 */
final class TransactionContextUser extends ContextDataWrapper implements TransactionContextUserInterface
{
    /**
     * @var TransactionContextUserData
     */
    private $data;

    public function __construct(Transaction $owner, TransactionContextUserData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if (is_string($id)) {
            $this->data->id = Tracer::limitKeywordString($id);
        } elseif (is_int($id)) {
            $this->data->id = $id;
        } elseif ($id === null) {
            $this->data->id = $id;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setEmail($email): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if (is_string($email)) {
            $this->data->email = Tracer::limitKeywordString($email);
        } elseif ($email === null) {
            $this->data->email = $email;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setUsername($username): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if (is_string($username)) {
            $this->data->username = Tracer::limitKeywordString($username);
        } elseif ($username === null) {
            $this->data->username = $username;
        }
    }
}
