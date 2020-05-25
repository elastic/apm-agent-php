<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

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
    private $serviceName;

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

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }
}
