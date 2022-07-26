<?php

namespace Symfony\Config\Doctrine\Orm\EntityManagerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DqlConfig 
{
    private $stringFunctions;
    private $numericFunctions;
    private $datetimeFunctions;
    private $_usedProperties = [];

    /**
     * @return $this
     */
    public function stringFunction(string $name, mixed $value): static
    {
        $this->_usedProperties['stringFunctions'] = true;
        $this->stringFunctions[$name] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function numericFunction(string $name, mixed $value): static
    {
        $this->_usedProperties['numericFunctions'] = true;
        $this->numericFunctions[$name] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function datetimeFunction(string $name, mixed $value): static
    {
        $this->_usedProperties['datetimeFunctions'] = true;
        $this->datetimeFunctions[$name] = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('string_functions', $value)) {
            $this->_usedProperties['stringFunctions'] = true;
            $this->stringFunctions = $value['string_functions'];
            unset($value['string_functions']);
        }

        if (array_key_exists('numeric_functions', $value)) {
            $this->_usedProperties['numericFunctions'] = true;
            $this->numericFunctions = $value['numeric_functions'];
            unset($value['numeric_functions']);
        }

        if (array_key_exists('datetime_functions', $value)) {
            $this->_usedProperties['datetimeFunctions'] = true;
            $this->datetimeFunctions = $value['datetime_functions'];
            unset($value['datetime_functions']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['stringFunctions'])) {
            $output['string_functions'] = $this->stringFunctions;
        }
        if (isset($this->_usedProperties['numericFunctions'])) {
            $output['numeric_functions'] = $this->numericFunctions;
        }
        if (isset($this->_usedProperties['datetimeFunctions'])) {
            $output['datetime_functions'] = $this->datetimeFunctions;
        }

        return $output;
    }

}
