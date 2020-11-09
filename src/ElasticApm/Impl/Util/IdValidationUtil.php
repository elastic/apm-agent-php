<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IdValidationUtil
{
    use StaticClassTrait;

    /**
     * @param string $numberAsString
     * @param int    $expectedSizeInBytes
     *
     * @return bool
     */
    public static function isValidHexNumberString(string $numberAsString, int $expectedSizeInBytes): bool
    {
        if (strlen($numberAsString) !== $expectedSizeInBytes * 2) {
            return false;
        }

        foreach (str_split($numberAsString) as $idChar) {
            if (!ctype_xdigit($idChar)) {
                return false;
            }
        }

        return true;
    }
}
