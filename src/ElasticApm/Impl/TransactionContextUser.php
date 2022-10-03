<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\TransactionContextUserInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ContextPartWrapper<Transaction>
 */
final class TransactionContextUser extends ContextPartWrapper implements TransactionContextUserInterface
{
    /** @var null|int|string */
    public $id;

    /** @var ?string */
    public $email;

    /** @var ?string */
    public $username;

    public function __construct(Transaction $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function setId($id): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->id = is_string($id) ? Tracer::limitKeywordString($id) : $id;
    }

    /** @inheritDoc */
    public function setEmail(?string $email): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->email = Tracer::limitNullableKeywordString($email);
    }

    /** @inheritDoc */
    public function setUsername(?string $username): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->username = Tracer::limitNullableKeywordString($username);
    }


    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return ($this->id !== null) || ($this->email !== null) || ($this->username !== null);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('email', $this->email, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('username', $this->username, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
