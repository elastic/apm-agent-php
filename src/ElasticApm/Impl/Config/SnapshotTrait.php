<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait SnapshotTrait
{
    /** @var array<string, mixed> */
    private $optNameToParsedValue;

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    protected function setPropertiesToValuesFrom(array $optNameToParsedValue): void
    {
        foreach ($optNameToParsedValue as $optName => $parsedValue) {
            $propertyName = TextUtil::snakeToCamelCase($optName);
            $actualClass = get_called_class();
            if (!property_exists($actualClass, $propertyName)) {
                throw new RuntimeException("Property `$propertyName' doesn't exist in class " . $actualClass);
            }
            $this->$propertyName = $parsedValue;
        }

        $this->optNameToParsedValue = $optNameToParsedValue;
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    public function getOptionValueByName(string $optName)
    {
        return ArrayUtil::getValueIfKeyExistsElse($optName, $this->optNameToParsedValue, null);
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $builder->add('optNameToParsedValue', $this->optNameToParsedValue);
        return $builder->build();
    }
}
