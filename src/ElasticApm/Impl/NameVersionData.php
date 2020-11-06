<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NameVersionData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string|null
     *
     * Name of an entity.
     *
     * The length of this string is limited to 1024.
     */
    public $name;

    /**
     * @var string|null
     *
     * Version of an entity, e.g."1.0.0".
     *
     * The length of this string is limited to 1024.
     */
    public $version;

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('version', $this->version, /* ref */ $result);

        return $result;
    }
}
