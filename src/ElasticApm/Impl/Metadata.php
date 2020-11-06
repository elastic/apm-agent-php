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
final class Metadata implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var ProcessData
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L22
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json
     */
    public $process;

    /**
     * @var ServiceData
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L7
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json
     */
    public $service;

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('process', $this->process, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('service', $this->service, /* ref */ $result);

        return $result;
    }
}
