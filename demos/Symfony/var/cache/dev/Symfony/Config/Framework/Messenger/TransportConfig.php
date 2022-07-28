<?php

namespace Symfony\Config\Framework\Messenger;

require_once __DIR__.\DIRECTORY_SEPARATOR.'TransportConfig'.\DIRECTORY_SEPARATOR.'RetryStrategyConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class TransportConfig 
{
    private $dsn;
    private $serializer;
    private $options;
    private $failureTransport;
    private $retryStrategy;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dsn($value): static
    {
        $this->_usedProperties['dsn'] = true;
        $this->dsn = $value;

        return $this;
    }

    /**
     * Service id of a custom serializer to use.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function serializer($value): static
    {
        $this->_usedProperties['serializer'] = true;
        $this->serializer = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function options(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['options'] = true;
        $this->options = $value;

        return $this;
    }

    /**
     * Transport name to send failed messages to (after all retries have failed).
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function failureTransport($value): static
    {
        $this->_usedProperties['failureTransport'] = true;
        $this->failureTransport = $value;

        return $this;
    }

    /**
     * @default {"service":null,"max_retries":3,"delay":1000,"multiplier":2,"max_delay":0}
     * @return \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig|$this
     */
    public function retryStrategy(mixed $value = []): \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['retryStrategy'] = true;
            $this->retryStrategy = $value;

            return $this;
        }

        if (!$this->retryStrategy instanceof \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig) {
            $this->_usedProperties['retryStrategy'] = true;
            $this->retryStrategy = new \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "retryStrategy()" has already been initialized. You cannot pass values the second time you call retryStrategy().');
        }

        return $this->retryStrategy;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('dsn', $value)) {
            $this->_usedProperties['dsn'] = true;
            $this->dsn = $value['dsn'];
            unset($value['dsn']);
        }

        if (array_key_exists('serializer', $value)) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = $value['serializer'];
            unset($value['serializer']);
        }

        if (array_key_exists('options', $value)) {
            $this->_usedProperties['options'] = true;
            $this->options = $value['options'];
            unset($value['options']);
        }

        if (array_key_exists('failure_transport', $value)) {
            $this->_usedProperties['failureTransport'] = true;
            $this->failureTransport = $value['failure_transport'];
            unset($value['failure_transport']);
        }

        if (array_key_exists('retry_strategy', $value)) {
            $this->_usedProperties['retryStrategy'] = true;
            $this->retryStrategy = \is_array($value['retry_strategy']) ? new \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig($value['retry_strategy']) : $value['retry_strategy'];
            unset($value['retry_strategy']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['dsn'])) {
            $output['dsn'] = $this->dsn;
        }
        if (isset($this->_usedProperties['serializer'])) {
            $output['serializer'] = $this->serializer;
        }
        if (isset($this->_usedProperties['options'])) {
            $output['options'] = $this->options;
        }
        if (isset($this->_usedProperties['failureTransport'])) {
            $output['failure_transport'] = $this->failureTransport;
        }
        if (isset($this->_usedProperties['retryStrategy'])) {
            $output['retry_strategy'] = $this->retryStrategy instanceof \Symfony\Config\Framework\Messenger\TransportConfig\RetryStrategyConfig ? $this->retryStrategy->toArray() : $this->retryStrategy;
        }

        return $output;
    }

}
