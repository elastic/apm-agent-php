<?php

namespace Symfony\Config\Framework\HttpClient\ScopedClientConfig\RetryFailed;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class HttpCodeConfig 
{
    private $code;
    private $methods;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function code($value): static
    {
        $this->_usedProperties['code'] = true;
        $this->code = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function methods(mixed $value): static
    {
        $this->_usedProperties['methods'] = true;
        $this->methods = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('code', $value)) {
            $this->_usedProperties['code'] = true;
            $this->code = $value['code'];
            unset($value['code']);
        }

        if (array_key_exists('methods', $value)) {
            $this->_usedProperties['methods'] = true;
            $this->methods = $value['methods'];
            unset($value['methods']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['code'])) {
            $output['code'] = $this->code;
        }
        if (isset($this->_usedProperties['methods'])) {
            $output['methods'] = $this->methods;
        }

        return $output;
    }

}
