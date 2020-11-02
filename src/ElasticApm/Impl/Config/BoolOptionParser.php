<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends EnumOptionParser<bool>
 */
final class BoolOptionParser extends EnumOptionParser
{
    /** @var array<string> */
    public static $trueRawValues = ['true', 'yes', 'on', '1'];

    /** @var array<string> */
    public static $falseRawValues = ['false', 'no', 'off', '0'];

    /**
     * @var array<array<string|bool>>
     * @phpstan-var array<array{string, bool}>
     */
    private static $boolNameToValue;

    public function __construct()
    {
        if (!isset(self::$boolNameToValue)) {
            self::$boolNameToValue = [];
            foreach (self::$trueRawValues as $trueRawValue) {
                self::$boolNameToValue[] = [$trueRawValue, true];
            }
            foreach (self::$falseRawValues as $falseRawValue) {
                self::$boolNameToValue[] = [$falseRawValue, false];
            }
        }

        parent::__construct(
            'bool' /* <- dbgEnumDesc */,
            self::$boolNameToValue /* <- nameToValue */,
            false /* <- isCaseSensitive */,
            false /* <- isUnambiguousPrefixAllowed */
        );
    }
}
