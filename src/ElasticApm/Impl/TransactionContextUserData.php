<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

final class TransactionContextUserData implements OptionalSerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var int|string|null
     */
    public $id;

    /**
     * @var string|null
     */
    public $email;

    /**
     * @var string|null
     */
    public $username;

    /**
     * {@inheritdoc}
     */
    public function prepareForSerialization(): bool
    {
        return isset($this->id) || isset($this->email) || isset($this->username);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('email', $this->email, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('username', $this->username, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
