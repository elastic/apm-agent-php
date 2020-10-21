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

    /** @var string */
    private $iniNamesPrefix;

    /**
     * @param string $iniNamesPrefix
     */
    public function __construct(string $iniNamesPrefix)
    {
        $this->iniNamesPrefix = $iniNamesPrefix;
    }

    public static function optionNameToIniName(string $iniNamesPrefix, string $optionName): string
    {
        return $iniNamesPrefix . $optionName;
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optionNameToValue = [];

        /** @var array<string, mixed> */
        $allOpts = ini_get_all(/* extension: */ null, /* details */ false);

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $iniName = self::optionNameToIniName($this->iniNamesPrefix, $optionName);
            if (!is_null($iniValue = ArrayUtil::getValueIfKeyExistsElse($iniName, $allOpts, null))) {
                $optionNameToValue[$optionName] = self::iniValueToString($iniValue);
            }
        }

        return new RawSnapshotFromArray($optionNameToValue);
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
