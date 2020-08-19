<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IniRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const DEFAULT_PREFIX = 'elastic_apm.';

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

        $pathToLoadedIniFile = php_ini_loaded_file();
        /** @var array<string, string> */
        $allOpts = $pathToLoadedIniFile === false
            ? []
            : parse_ini_file($pathToLoadedIniFile, /* process_sections: */ false, INI_SCANNER_RAW);

        foreach ($this->optionToIniName as $optName => $iniName) {
            if (array_key_exists($iniName, $allOpts)) {
                $optNameToIniValue[$optName] = $allOpts[$iniName];
            }
        }

        return new RawSnapshotFromArray($optNameToIniValue);
    }
}
