<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use ReflectionClass;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait ObjectToStringUsingPropertiesTrait
{
    /**
     * @param array<string> $excludedProperties
     *
     * @return string
     */
    public function toStringUsingProperties(array $excludedProperties = []): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class($this)));

        $currentClass = new ReflectionClass(get_class($this));
        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                $reflectionProperty->setAccessible(true);
                $propName = $reflectionProperty->getName();
                $propValue = $reflectionProperty->getValue($this);
                if (!in_array($propName, $excludedProperties, /* strict */ true)) {
                    $builder->add($propName, $propValue);
                }
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }

        return $builder->build();
    }

    public function __toString(): string
    {
        return $this->toStringUsingProperties();
    }
}
