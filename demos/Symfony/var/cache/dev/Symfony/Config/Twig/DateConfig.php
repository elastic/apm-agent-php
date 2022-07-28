<?php

namespace Symfony\Config\Twig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DateConfig 
{
    private $format;
    private $intervalFormat;
    private $timezone;
    private $_usedProperties = [];

    /**
     * @default 'F j, Y H:i'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function format($value): static
    {
        $this->_usedProperties['format'] = true;
        $this->format = $value;

        return $this;
    }

    /**
     * @default '%d days'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function intervalFormat($value): static
    {
        $this->_usedProperties['intervalFormat'] = true;
        $this->intervalFormat = $value;

        return $this;
    }

    /**
     * The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function timezone($value): static
    {
        $this->_usedProperties['timezone'] = true;
        $this->timezone = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('format', $value)) {
            $this->_usedProperties['format'] = true;
            $this->format = $value['format'];
            unset($value['format']);
        }

        if (array_key_exists('interval_format', $value)) {
            $this->_usedProperties['intervalFormat'] = true;
            $this->intervalFormat = $value['interval_format'];
            unset($value['interval_format']);
        }

        if (array_key_exists('timezone', $value)) {
            $this->_usedProperties['timezone'] = true;
            $this->timezone = $value['timezone'];
            unset($value['timezone']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['format'])) {
            $output['format'] = $this->format;
        }
        if (isset($this->_usedProperties['intervalFormat'])) {
            $output['interval_format'] = $this->intervalFormat;
        }
        if (isset($this->_usedProperties['timezone'])) {
            $output['timezone'] = $this->timezone;
        }

        return $output;
    }

}
