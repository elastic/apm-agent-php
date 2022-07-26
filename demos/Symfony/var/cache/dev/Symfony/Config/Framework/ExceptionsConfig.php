<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ExceptionsConfig 
{
    private $logLevel;
    private $statusCode;
    private $_usedProperties = [];

    /**
     * The level of log message. Null to let Symfony decide.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function logLevel($value): static
    {
        $this->_usedProperties['logLevel'] = true;
        $this->logLevel = $value;

        return $this;
    }

    /**
     * The status code of the response. Null to let Symfony decide.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function statusCode($value): static
    {
        $this->_usedProperties['statusCode'] = true;
        $this->statusCode = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('log_level', $value)) {
            $this->_usedProperties['logLevel'] = true;
            $this->logLevel = $value['log_level'];
            unset($value['log_level']);
        }

        if (array_key_exists('status_code', $value)) {
            $this->_usedProperties['statusCode'] = true;
            $this->statusCode = $value['status_code'];
            unset($value['status_code']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['logLevel'])) {
            $output['log_level'] = $this->logLevel;
        }
        if (isset($this->_usedProperties['statusCode'])) {
            $output['status_code'] = $this->statusCode;
        }

        return $output;
    }

}
