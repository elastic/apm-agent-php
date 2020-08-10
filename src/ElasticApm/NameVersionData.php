<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use JsonSerializable;

class NameVersionData implements JsonSerializable
{
    /** @var string|null */
    private $name;

    /** @var string|null */
    private $version;

    public function __construct(?string $name = null, ?string $version = null)
    {
        $this->setName($name);
        $this->setVersion($version);
    }

    /**
     * Name of an entity.
     *
     * The length of this string is limited to 1024.
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
     * Version of an entity, e.g."1.0.0".
     *
     * The length of this string is limited to 1024.
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

    public function isEmpty(): bool
    {
        return is_null($this->name) && is_null($this->version);
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
                'name'    => $this->name,
                'version' => $this->version
            ]
        );
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class()));
        $builder->add('name', $this->name);
        $builder->add('version', $this->version);
        return $builder->build();
    }
}
