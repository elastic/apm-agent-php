<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\SnapshotTrait;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ConfigSnapshot
{
    use SnapshotTrait;

    /** @var int */
    private $appCodeHostKind;

    /** @var string|null */
    private $appCodePhpExe;

    /** @var string|null */
    private $appCodePhpIni;

    /** @var int */
    private $logLevel;

    /** @var int */
    private $mockApmServerPort;

    /** @var int */
    private $spawnedProcessesCleanerPort;

    /** @var string */
    private $testEnvId;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function appCodeHostKind(): int
    {
        return $this->appCodeHostKind;
    }

    public function appCodePhpExe(): ?string
    {
        return $this->appCodePhpExe;
    }

    public function appCodePhpIni(): ?string
    {
        return $this->appCodePhpIni;
    }

    public function logLevel(): int
    {
        return $this->logLevel;
    }

    public function mockApmServerPort(): int
    {
        return $this->mockApmServerPort;
    }

    public function spawnedProcessesCleanerPort(): int
    {
        return $this->spawnedProcessesCleanerPort;
    }

    public function testEnvId(): string
    {
        return $this->testEnvId;
    }
}
