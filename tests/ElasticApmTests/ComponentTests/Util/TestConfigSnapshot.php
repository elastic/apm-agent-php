<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\SnapshotTrait;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TestConfigSnapshot
{
    use SnapshotTrait;

    /** @var int */
    public $appCodeHostKind;

    /** @var string|null */
    public $appCodePhpExe;

    /** @var string|null */
    public $appCodePhpIni;

    /** @var int */
    public $logLevel;

    /** @var SharedDataPerProcess */
    public $sharedDataPerProcess;

    /** @var SharedDataPerRequest */
    public $sharedDataPerRequest;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->sharedDataPerProcess = new SharedDataPerProcess();
        $this->sharedDataPerRequest = new SharedDataPerRequest();

        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }
}
