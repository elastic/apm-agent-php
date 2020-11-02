<?php

/** @noinspection PhpDocMissingThrowsInspection, PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use ReflectionClass;

trait DeserializableDataObjectTrait
{
    /**
     * @param array<string, mixed> $decodedJson
     */
    public function deserializeFrom(array $decodedJson): void
    {
        $currentClass = new ReflectionClass(get_class($this));
        foreach ($currentClass->getProperties() as $reflectionProperty) {
            $propName = $reflectionProperty->getName();
            if (!array_key_exists($propName, $decodedJson)) {
                continue;
            }

            $reflectionProperty->setValue($this, $decodedJson[$propName]);
        }
    }
}
