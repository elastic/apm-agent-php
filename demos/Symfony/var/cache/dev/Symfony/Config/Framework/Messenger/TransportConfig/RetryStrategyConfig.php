<?php

namespace Symfony\Config\Framework\Messenger\TransportConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class RetryStrategyConfig 
{
    private $service;
    private $maxRetries;
    private $delay;
    private $multiplier;
    private $maxDelay;
    private $_usedProperties = [];

    /**
     * Service id to override the retry strategy entirely
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function service($value): static
    {
        $this->_usedProperties['service'] = true;
        $this->service = $value;

        return $this;
    }

    /**
     * @default 3
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxRetries($value): static
    {
        $this->_usedProperties['maxRetries'] = true;
        $this->maxRetries = $value;

        return $this;
    }

    /**
     * Time in ms to delay (or the initial value when multiplier is used)
     * @default 1000
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function delay($value): static
    {
        $this->_usedProperties['delay'] = true;
        $this->delay = $value;

        return $this;
    }

    /**
     * If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries))
     * @default 2
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function multiplier($value): static
    {
        $this->_usedProperties['multiplier'] = true;
        $this->multiplier = $value;

        return $this;
    }

    /**
     * Max time in ms that a retry should ever be delayed (0 = infinite)
     * @default 0
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxDelay($value): static
    {
        $this->_usedProperties['maxDelay'] = true;
        $this->maxDelay = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('max_retries', $value)) {
            $this->_usedProperties['maxRetries'] = true;
            $this->maxRetries = $value['max_retries'];
            unset($value['max_retries']);
        }

        if (array_key_exists('delay', $value)) {
            $this->_usedProperties['delay'] = true;
            $this->delay = $value['delay'];
            unset($value['delay']);
        }

        if (array_key_exists('multiplier', $value)) {
            $this->_usedProperties['multiplier'] = true;
            $this->multiplier = $value['multiplier'];
            unset($value['multiplier']);
        }

        if (array_key_exists('max_delay', $value)) {
            $this->_usedProperties['maxDelay'] = true;
            $this->maxDelay = $value['max_delay'];
            unset($value['max_delay']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['maxRetries'])) {
            $output['max_retries'] = $this->maxRetries;
        }
        if (isset($this->_usedProperties['delay'])) {
            $output['delay'] = $this->delay;
        }
        if (isset($this->_usedProperties['multiplier'])) {
            $output['multiplier'] = $this->multiplier;
        }
        if (isset($this->_usedProperties['maxDelay'])) {
            $output['max_delay'] = $this->maxDelay;
        }

        return $output;
    }

}
