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

    /** @var string|null */
    private $appCodeClass;

    /** @var int */
    private $appCodeHostKind;

    /** @var string|null */
    private $appCodeMethod;

    /** @var string|null */
    private $appCodePhpExe;

    /** @var string|null */
    private $appCodePhpIni;

    /** @var int */
    private $logLevel;

    /** @var int */
    private $resourcesCleanerPort;

    /** @var string */
    private $resourcesCleanerServerId;

    /** @var int */
    private $rootProcessId;

    /** @var int */
    private $thisServerPort;

    /** @var string */
    private $thisServerId;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function appCodeClass(): ?string
    {
        return $this->appCodeClass;
    }

    public function appCodeHostKind(): int
    {
        return $this->appCodeHostKind;
    }

    public function appCodeMethod(): ?string
    {
        return $this->appCodeMethod;
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

    public function resourcesCleanerPort(): int
    {
        return $this->resourcesCleanerPort;
    }

    public function resourcesCleanerServerId(): string
    {
        return $this->resourcesCleanerServerId;
    }

    public function rootProcessId(): int
    {
        return $this->rootProcessId;
    }

    public function thisServerId(): string
    {
        return $this->thisServerId;
    }

    public function thisServerPort(): int
    {
        return $this->thisServerPort;
    }
}
