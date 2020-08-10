<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use JsonSerializable;

/**
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json
 */
class ServiceData implements JsonSerializable
{
    public const DEFAULT_SERVICE_NAME = 'Unnamed PHP service';
    public const DEFAULT_AGENT_NAME = 'php';
    public const DEFAULT_LANGUAGE_NAME = 'PHP';

    /** @var string|null */
    private $name = null;

    /** @var string|null */
    private $version = null;

    /** @var string|null */
    private $environment = null;

    /** @var NameVersionData */
    private $agent;

    /** @var NameVersionData */
    private $framework;

    /** @var NameVersionData */
    private $language;

    /** @var NameVersionData */
    private $runtime;

    public function __construct()
    {
        $this->agent = new NameVersionData();
        $this->framework = new NameVersionData();
        $this->language = new NameVersionData();
        $this->runtime = new NameVersionData();
    }

    /**
     * Immutable name of the service emitting this event.
     * Valid characters are: 'a'-'z', 'A'-'Z', '0'-'9', '_' and '-'.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L50
     *
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = TextUtil::limitNullableKeywordString($name);
    }

    /**
     * @see setName() For the description
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Version of the service emitting this event.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L75
     *
     * @param string|null $version
     */
    public function setVersion(?string $version): void
    {
        $this->version = TextUtil::limitNullableKeywordString($version);
    }

    /**
     * @see setVersion() For the description
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Environment name of the service, e.g. "production" or "staging".
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L56
     *
     * @param string|null $environment
     */
    public function setEnvironment(?string $environment): void
    {
        $this->environment = TextUtil::limitNullableKeywordString($environment);
    }

    /**
     * @see setEnvironment() For the description
     */
    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * Name and version of the Elastic APM agent.
     * Name of the Elastic APM agent, e.g. "php".
     * Version of the Elastic APM agent, e.g."1.0.0".
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L6
     */
    public function agent(): NameVersionData
    {
        return $this->agent;
    }

    /**
     * Name and version of the web framework used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L22
     */
    public function framework(): NameVersionData
    {
        return $this->framework;
    }

    /**
     * Name and version of the programming language used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L36
     */
    public function language(): NameVersionData
    {
        return $this->language;
    }

    /**
     * Name and version of the language runtime running this service.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L61
     */
    public function runtime(): NameVersionData
    {
        return $this->runtime;
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResult(
            [
                'name'        => $this->name,
                'version'     => $this->version,
                'environment' => $this->environment,
                'agent'       => SerializationUtil::nullIfEmpty($this->agent),
                'framework'   => SerializationUtil::nullIfEmpty($this->framework),
                'language'    => SerializationUtil::nullIfEmpty($this->language),
                'runtime'     => SerializationUtil::nullIfEmpty($this->runtime),
            ]
        );
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class()));
        $builder->add('name', $this->name);
        $builder->add('version', $this->version);
        $builder->add('environment', $this->environment);
        $builder->add('framework', $this->framework());
        $builder->add('language', $this->language());
        $builder->add('runtime', $this->runtime());
        return $builder->build();
    }
}
