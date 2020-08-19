<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Snapshot
{
    use SnapshotTrait;

    /** @var bool */
    private $enabled;

    /** @var string|null */
    private $environment;

    /** @var string|null */
    private $serviceName;

    /** @var string|null */
    private $serviceNodeName;

    /** @var string|null */
    private $serviceVersion;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function environment(): ?string
    {
        return $this->environment;
    }

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }

    public function serviceNodeName(): ?string
    {
        return $this->serviceNodeName;
    }

    public function serviceVersion(): ?string
    {
        return $this->serviceVersion;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();
        $builder->add('enabled', $this->enabled);
        $builder->add('environment', $this->environment);
        $builder->add('serviceName', $this->serviceName);
        $builder->add('serviceNodeName', $this->serviceNodeName);
        $builder->add('serviceVersion', $this->serviceVersion);
        return $builder->build();
    }
}
