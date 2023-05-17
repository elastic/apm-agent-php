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
class ServiceData implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var ServiceAgentData|null
     *
     * Name and version of the Elastic APM agent.
     * Name of the Elastic APM agent, e.g. "php".
     * Version of the Elastic APM agent, e.g."1.0.0".
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L10
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L15
     */
    public $agent = null;

    /**
     * @var string|null
     *
     * Environment name of the service, e.g. "production" or "staging".
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L56
     */
    public $environment = null;

    /**
     * @var NameVersionData|null
     *
     * Name and version of the web framework used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L26
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L30
     */
    public $framework = null;

    /**
     * @var NameVersionData|null
     *
     * Name and version of the programming language used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L40
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L44
     */
    public $language = null;

    /**
     * @var string
     *
     * Immutable name of the service emitting this event.
     * Valid characters are: 'a'-'z', 'A'-'Z', '0'-'9', '_' and '-'.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L50
     */
    public $name;

    /**
     * @var string|null
     *
     * Unique meaningful name of the service node.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.5.0/docs/spec/service.json#L85
     */
    public $nodeConfiguredName = null;

    /**
     * @var NameVersionData|null
     *
     * Name and version of the language runtime running this service.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L65
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L69
     */
    public $runtime = null;

    /**
     * @var string|null
     *
     * Version of the service emitting this event.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L75
     */
    public $version = null;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('agent', $this->agent, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('environment', $this->environment, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('framework', $this->framework, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('language', $this->language, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('name', $this->name, /* ref */ $result);

        if ($this->nodeConfiguredName !== null) {
            $nodeSubObject = ['configured_name' => $this->nodeConfiguredName];
            SerializationUtil::addNameValue('node', $nodeSubObject, /* ref */ $result);
        }

        SerializationUtil::addNameValueIfNotNull('runtime', $this->runtime, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('version', $this->version, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
