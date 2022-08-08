<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ProfilerConfig 
{
    private $enabled;
    private $collect;
    private $collectParameter;
    private $onlyExceptions;
    private $onlyMainRequests;
    private $dsn;
    private $collectSerializerData;
    private $_usedProperties = [];

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enabled($value): static
    {
        $this->_usedProperties['enabled'] = true;
        $this->enabled = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function collect($value): static
    {
        $this->_usedProperties['collect'] = true;
        $this->collect = $value;

        return $this;
    }

    /**
     * The name of the parameter to use to enable or disable collection on a per request basis
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function collectParameter($value): static
    {
        $this->_usedProperties['collectParameter'] = true;
        $this->collectParameter = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function onlyExceptions($value): static
    {
        $this->_usedProperties['onlyExceptions'] = true;
        $this->onlyExceptions = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function onlyMainRequests($value): static
    {
        $this->_usedProperties['onlyMainRequests'] = true;
        $this->onlyMainRequests = $value;

        return $this;
    }

    /**
     * @default 'file:%kernel.cache_dir%/profiler'
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
     * Enables the serializer data collector and profiler panel
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function collectSerializerData($value): static
    {
        $this->_usedProperties['collectSerializerData'] = true;
        $this->collectSerializerData = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('collect', $value)) {
            $this->_usedProperties['collect'] = true;
            $this->collect = $value['collect'];
            unset($value['collect']);
        }

        if (array_key_exists('collect_parameter', $value)) {
            $this->_usedProperties['collectParameter'] = true;
            $this->collectParameter = $value['collect_parameter'];
            unset($value['collect_parameter']);
        }

        if (array_key_exists('only_exceptions', $value)) {
            $this->_usedProperties['onlyExceptions'] = true;
            $this->onlyExceptions = $value['only_exceptions'];
            unset($value['only_exceptions']);
        }

        if (array_key_exists('only_main_requests', $value)) {
            $this->_usedProperties['onlyMainRequests'] = true;
            $this->onlyMainRequests = $value['only_main_requests'];
            unset($value['only_main_requests']);
        }

        if (array_key_exists('dsn', $value)) {
            $this->_usedProperties['dsn'] = true;
            $this->dsn = $value['dsn'];
            unset($value['dsn']);
        }

        if (array_key_exists('collect_serializer_data', $value)) {
            $this->_usedProperties['collectSerializerData'] = true;
            $this->collectSerializerData = $value['collect_serializer_data'];
            unset($value['collect_serializer_data']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['enabled'])) {
            $output['enabled'] = $this->enabled;
        }
        if (isset($this->_usedProperties['collect'])) {
            $output['collect'] = $this->collect;
        }
        if (isset($this->_usedProperties['collectParameter'])) {
            $output['collect_parameter'] = $this->collectParameter;
        }
        if (isset($this->_usedProperties['onlyExceptions'])) {
            $output['only_exceptions'] = $this->onlyExceptions;
        }
        if (isset($this->_usedProperties['onlyMainRequests'])) {
            $output['only_main_requests'] = $this->onlyMainRequests;
        }
        if (isset($this->_usedProperties['dsn'])) {
            $output['dsn'] = $this->dsn;
        }
        if (isset($this->_usedProperties['collectSerializerData'])) {
            $output['collect_serializer_data'] = $this->collectSerializerData;
        }

        return $output;
    }

}
