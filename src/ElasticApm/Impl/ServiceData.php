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
class ServiceData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string|null
     *
     * Immutable name of the service emitting this event.
     * Valid characters are: 'a'-'z', 'A'-'Z', '0'-'9', '_' and '-'.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L50
     */
    public $name = null;

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
     * @var NameVersionData|null
     *
     * Name and version of the language runtime running this service.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L65
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L69
     */
    public $runtime = null;

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('version', $this->version, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('environment', $this->environment, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('agent', $this->agent, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('framework', $this->framework, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('language', $this->language, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('runtime', $this->runtime, /* ref */ $result);

        return $result;
    }
}
