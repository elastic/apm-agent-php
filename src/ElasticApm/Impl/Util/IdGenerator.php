<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IdGenerator
{
    use StaticClassTrait;

    /** @var int */
    public const TRACE_ID_SIZE_IN_BYTES = 16;

    public static function generateId(int $idLengthInBytes): string
    {
        return self::convertBinaryIdToString(self::generateBinaryId($idLengthInBytes));
    }

    /**
     * @param array<int> $binaryId
     *
     * @return string
     */
    private static function convertBinaryIdToString(array $binaryId): string
    {
        $result = '';
        for ($i = 0; $i < count($binaryId); ++$i) {
            $result .= sprintf('%02x', $binaryId[$i]);
        }
        return $result;
    }

    /**
     * @param int $idLengthInBytes
     *
     * @return array<int>
     */
    private static function generateBinaryId(int $idLengthInBytes): array
    {
        $result = [];
        for ($i = 0; $i < $idLengthInBytes; ++$i) {
            $result[] = mt_rand(0, 255);
        }
        return $result;
    }
}
