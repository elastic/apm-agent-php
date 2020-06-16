<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NameVersionData extends EventData implements NameVersionDataInterface
{
    /** @var string|null */
    protected $name;

    /** @var string|null */
    protected $version;

    public function __construct(?string $name = null, ?string $version = null)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /** @inheritDoc */
    public function name(): ?string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function version(): ?string
    {
        return $this->version;
    }

    public static function dataToString(NameVersionDataInterface $data, ?string $type = null): string
    {
        $builder = new ObjectToStringBuilder();
        $builder->add('name', $data->name());
        $builder->add('version', $data->version());
        return $builder->build();
    }

    public function __toString(): string
    {
        return self::dataToString($this);
    }
}
