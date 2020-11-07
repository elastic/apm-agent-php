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
class ServiceAgentData extends NameVersionData
{
    /**
     * @var string|null
     *
     * Free format ID used for metrics correlation by some agents.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.2/docs/spec/service.json#L20
     */
    public $ephemeralId = null;

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValueIfNotNull('ephemeral_id', $this->ephemeralId, /* ref */ $result);

        return $result;
    }
}
