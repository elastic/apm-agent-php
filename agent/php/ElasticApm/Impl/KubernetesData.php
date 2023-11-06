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
class KubernetesData implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string|null
     *
     * Kubernetes namespace.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L43
     */
    public $namespace = null;

    /**
     * @var string|null
     *
     * Kubernetes pod name.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L50
     */
    public $podName = null;

    /**
     * @var string|null
     *
     * Kubernetes pod uid.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L55
     */
    public $podUid = null;

    /**
     * @var string|null
     *
     * Kubernetes node name.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.4.0/docs/spec/system.json#L64
     */
    public $nodeName = null;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('namespace', $this->namespace, /* ref */ $result);

        if ($this->podName !== null || $this->podUid !== null) {
            $podSubObject = ['name' => $this->podName, 'uid' => $this->podUid];
            SerializationUtil::addNameValue('pod', $podSubObject, /* ref */ $result);
        }

        if ($this->nodeName !== null) {
            $nodeSubObject = ['name' => $this->nodeName];
            SerializationUtil::addNameValue('node', $nodeSubObject, /* ref */ $result);
        }

        return SerializationUtil::postProcessResult($result);
    }
}
