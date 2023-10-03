<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SystemData implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string|null
     *
     * The length of this string is limited to 1024.
     *
     * CPU Architecture.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L6
     */

    public $architecture = null;
    /**
     * @var string|null
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/system.json#L11
     * Hostname of the system the agent is running on
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L11
     * Deprecated
     */
    public $hostname = null;

    /**
     * @var string|null
     *
     * Hostname of the host the monitored service is running on.
     * It normally contains what the hostname command returns on the host machine.
     * Will be ignored if kubernetes information is set, otherwise should always be set.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L16
     */
    public $detectedHostname = null;

    /**
     * @var string|null
     *
     * The length of this string is limited to 1024.
     *
     * Name of the host the monitored service is running on.
     * It should only be set when configured by the user.
     * If empty, will be set to detected_hostname or derived from kubernetes information if provided.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L21
     */
    public $configuredHostname = null;

    /**
     * @var string|null
     *
     * The length of this string is limited to 1024.
     *
     * Container ID.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L31
     */
    public $containerId = null;

    /**
     * @var string|null
     *
     * The length of this string is limited to 1024.
     *
     * Name of the system platform.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L26
     */
    public $platform = null;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('hostname', $this->hostname, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('detected_hostname', $this->detectedHostname, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('configured_hostname', $this->configuredHostname, /* ref */ $result);

        if ($this->containerId !== null) {
            $containerSubObject = ['id' => $this->containerId];
            SerializationUtil::addNameValue('container', $containerSubObject, /* ref */ $result);
        }

        SerializationUtil::addNameValueIfNotNull('architecture', $this->architecture, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('platform', $this->platform, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
