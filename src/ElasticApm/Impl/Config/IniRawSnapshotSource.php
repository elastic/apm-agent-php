<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IniRawSnapshotSource implements RawSnapshotSourceInterface
{
    /** @var array<string, string> */
    private $optionToIniName;

    /**
     * @param string        $iniPrefix
     * @param array<string> $optionNames
     */
    public function __construct(string $iniPrefix, array $optionNames)
    {
        $this->optionToIniName = [];
        foreach ($optionNames as $optName) {
            $this->optionToIniName[$optName] = $iniPrefix . $optName;
        }
    }

    public function currentSnapshot(): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optNameToIniValue = [];

        foreach ($this->optionToIniName as $optName => $iniName) {
            $iniValue = ini_get($iniName);
            if ($iniValue !== false) {
                $optNameToIniValue[$optName] = $iniValue;
            }
        }

        return new RawSnapshotFromArray($optNameToIniValue);
    }
}
