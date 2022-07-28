<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PhpErrorsConfig 
{
    private $log;
    private $throw;
    private $_usedProperties = [];

    /**
     * Use the application logger instead of the PHP logger for logging PHP errors.
     * @example "true" to use the default configuration: log all errors. "false" to disable. An integer bit field of E_* constants, or an array mapping E_* constants to log levels.
     * @default true
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function log(mixed $value = true): static
    {
        $this->_usedProperties['log'] = true;
        $this->log = $value;

        return $this;
    }

    /**
     * Throw PHP errors as \ErrorException instances.
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function throw($value): static
    {
        $this->_usedProperties['throw'] = true;
        $this->throw = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('log', $value)) {
            $this->_usedProperties['log'] = true;
            $this->log = $value['log'];
            unset($value['log']);
        }

        if (array_key_exists('throw', $value)) {
            $this->_usedProperties['throw'] = true;
            $this->throw = $value['throw'];
            unset($value['throw']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['log'])) {
            $output['log'] = $this->log;
        }
        if (isset($this->_usedProperties['throw'])) {
            $output['throw'] = $this->throw;
        }

        return $output;
    }

}
