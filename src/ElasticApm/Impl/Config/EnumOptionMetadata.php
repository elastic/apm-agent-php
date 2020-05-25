<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<int>
 */
class EnumOptionMetadata extends OptionMetadataBase
{
    /** @var string */
    private $dbgEnumDesc;

    /**
     * @var array<string, mixed>
     * @phpstan-var array<string, int>
     */
    private $nameToValue;

    /**
     * @param string             $dbgEnumDesc
     * @param int                $defaultValue
     * @param array<string, int> $nameToValue
     */
    public function __construct(string $dbgEnumDesc, $defaultValue, array $nameToValue)
    {
        parent::__construct($defaultValue);
        $this->dbgEnumDesc = $dbgEnumDesc;
        $this->nameToValue = $nameToValue;
    }

    /**
     * @param string $rawValue
     *
     * @return int
     */
    public function parse(string $rawValue)
    {
        foreach ($this->nameToValue as $enumName => $enumVal) {
            if (strnatcasecmp($rawValue, $enumName) === 0) {
                return $enumVal;
            }
        }

        throw new ParseException("Not a valid ' . $this->dbgEnumDesc . ' value. Raw option value: `$rawValue'");
    }
}
