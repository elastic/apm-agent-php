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

    /** @var string */
    private $envVarNamesPrefix;

    /**
     * @param string $envVarNamesPrefix
     */
    public function __construct(string $envVarNamesPrefix)
    {
        $this->envVarNamesPrefix = $envVarNamesPrefix;
    }

    public static function optionNameToEnvVarName(string $envVarNamesPrefix, string $optionName): string
    {
        return $envVarNamesPrefix . strtoupper($optionName);
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optionNameToEnvVarValue = [];

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $envVarValue = getenv(
                self::optionNameToEnvVarName($this->envVarNamesPrefix, $optionName),
                /* local_only: */ true
            );
            if ($envVarValue !== false) {
                $optionNameToEnvVarValue[$optionName] = $envVarValue;
            }
        }

        return new RawSnapshotFromArray($optionNameToEnvVarValue);
    }
}
