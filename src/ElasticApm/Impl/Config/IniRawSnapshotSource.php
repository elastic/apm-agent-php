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

        /** @var array<string, mixed> */
        $allOpts = ini_get_all(/* extension: */ null, /* details */ false);

        foreach ($this->optionToIniName as $optName => $iniName) {
            if (!is_null($inValue = ArrayUtil::getValueIfKeyExistsElse($iniName, $allOpts, null))) {
                $optNameToIniValue[$optName] = self::iniValueToString($inValue);
            } else {
                var_dump($optName, $iniName);
            }
        }

        return new RawSnapshotFromArray($optNameToIniValue);
    }

    /**
     * @param mixed $iniValue
     *
     * @return string
     */
    private static function iniValueToString($iniValue): string
    {
        if (is_bool($iniValue)) {
            return $iniValue ? 'true' : 'false';
        }

        return strval($iniValue);
    }
}
