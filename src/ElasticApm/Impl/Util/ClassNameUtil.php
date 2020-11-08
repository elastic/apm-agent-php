<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ClassNameUtil
{
    use StaticClassTrait;

    public static function fqToShort(string $fqClassName): string
    {
        $namespace = '';
        $shortName = '';
        self::splitFqClassName($fqClassName, /* ref */ $namespace, /* ref */ $shortName);
        return $shortName;
    }

    public static function splitFqClassName(string $fqName, string &$namespace, string &$shortName): void
    {
        // Check if $fqName begin with a back slash(es)
        $firstBackSlashPos = strpos($fqName, '\\');
        if ($firstBackSlashPos === false) {
            $namespace = '';
            $shortName = $fqName;
            return;
        }
        $firstCanonPos = $firstBackSlashPos === 0 ? 1 : 0;

        $lastBackSlashPos = strrpos($fqName, '\\', $firstCanonPos);
        if ($lastBackSlashPos === false) {
            $namespace = '';
            $shortName = substr($fqName, $firstCanonPos);
            return;
        }

        $namespace = substr($fqName, $firstCanonPos, $lastBackSlashPos - $firstCanonPos);
        $shortName = substr($fqName, $lastBackSlashPos + 1);
    }
}
