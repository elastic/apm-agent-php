<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ServiceNodeData extends EventData implements JsonSerializable
{
    /** @var string|null */
    protected $configuredName = null;

    public function getConfiguredName(): ?string
    {
        return $this->configuredName;
    }

    public function setConfiguredName(?string $configuredName): void
    {
        $this->configuredName = Tracer::limitNullableKeywordString($configuredName);
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();
        $builder->add('configuredName', $this->configuredName);
        return $builder->build();
    }
}
