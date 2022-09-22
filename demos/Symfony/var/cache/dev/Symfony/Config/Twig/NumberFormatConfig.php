<?php

namespace Symfony\Config\Twig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class NumberFormatConfig 
{
    private $decimals;
    private $decimalPoint;
    private $thousandsSeparator;
    private $_usedProperties = [];

    /**
     * @default 0
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function decimals($value): static
    {
        $this->_usedProperties['decimals'] = true;
        $this->decimals = $value;

        return $this;
    }

    /**
     * @default '.'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function decimalPoint($value): static
    {
        $this->_usedProperties['decimalPoint'] = true;
        $this->decimalPoint = $value;

        return $this;
    }

    /**
     * @default ','
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function thousandsSeparator($value): static
    {
        $this->_usedProperties['thousandsSeparator'] = true;
        $this->thousandsSeparator = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('decimals', $value)) {
            $this->_usedProperties['decimals'] = true;
            $this->decimals = $value['decimals'];
            unset($value['decimals']);
        }

        if (array_key_exists('decimal_point', $value)) {
            $this->_usedProperties['decimalPoint'] = true;
            $this->decimalPoint = $value['decimal_point'];
            unset($value['decimal_point']);
        }

        if (array_key_exists('thousands_separator', $value)) {
            $this->_usedProperties['thousandsSeparator'] = true;
            $this->thousandsSeparator = $value['thousands_separator'];
            unset($value['thousands_separator']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['decimals'])) {
            $output['decimals'] = $this->decimals;
        }
        if (isset($this->_usedProperties['decimalPoint'])) {
            $output['decimal_point'] = $this->decimalPoint;
        }
        if (isset($this->_usedProperties['thousandsSeparator'])) {
            $output['thousands_separator'] = $this->thousandsSeparator;
        }

        return $output;
    }

}
