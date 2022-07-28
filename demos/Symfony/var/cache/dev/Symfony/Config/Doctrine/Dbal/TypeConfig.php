<?php

namespace Symfony\Config\Doctrine\Dbal;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class TypeConfig 
{
    private $class;
    private $commented;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function class($value): static
    {
        $this->_usedProperties['class'] = true;
        $this->class = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @deprecated The doctrine-bundle type commenting features were removed; the corresponding config parameter was deprecated in 2.0 and will be dropped in 3.0.
     * @return $this
     */
    public function commented($value): static
    {
        $this->_usedProperties['commented'] = true;
        $this->commented = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('class', $value)) {
            $this->_usedProperties['class'] = true;
            $this->class = $value['class'];
            unset($value['class']);
        }

        if (array_key_exists('commented', $value)) {
            $this->_usedProperties['commented'] = true;
            $this->commented = $value['commented'];
            unset($value['commented']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['class'])) {
            $output['class'] = $this->class;
        }
        if (isset($this->_usedProperties['commented'])) {
            $output['commented'] = $this->commented;
        }

        return $output;
    }

}
