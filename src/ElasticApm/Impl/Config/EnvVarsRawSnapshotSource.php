<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnvVarsRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const DEFAULT_NAME_PREFIX = 'ELASTIC_APM_';

    /** @var array<string, string> */
    private $optionToEnvVarName;

    /**
     * @param string        $envVarPrefix
     * @param array<string> $optionNames
     */
    public function __construct(string $envVarPrefix, array $optionNames)
    {
        $this->optionToEnvVarName = [];
        foreach ($optionNames as $optName) {
            $this->optionToEnvVarName[$optName] = self::optionNameToEnvVarName($envVarPrefix, $optName);
        }
    }

    public static function optionNameToEnvVarName(string $envVarPrefix, string $optionName): string
    {
        return $envVarPrefix . strtoupper($optionName);
    }

    public function currentSnapshot(): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optNameToEnvVarValue = [];

        foreach ($this->optionToEnvVarName as $optName => $envVarName) {
            $envVarValue = getenv($envVarName, /* local_only: */ true);
            if ($envVarValue !== false) {
                $optNameToEnvVarValue[$optName] = $envVarValue;
            }
        }

        return new RawSnapshotFromArray($optNameToEnvVarValue);
    }
}
