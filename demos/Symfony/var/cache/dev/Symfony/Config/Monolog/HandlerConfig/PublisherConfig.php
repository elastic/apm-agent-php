<?php

namespace Symfony\Config\Monolog\HandlerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class PublisherConfig 
{
    private $id;
    private $hostname;
    private $port;
    private $chunkSize;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function id($value): static
    {
        $this->_usedProperties['id'] = true;
        $this->id = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function hostname($value): static
    {
        $this->_usedProperties['hostname'] = true;
        $this->hostname = $value;

        return $this;
    }

    /**
     * @default 12201
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function port($value): static
    {
        $this->_usedProperties['port'] = true;
        $this->port = $value;

        return $this;
    }

    /**
     * @default 1420
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function chunkSize($value): static
    {
        $this->_usedProperties['chunkSize'] = true;
        $this->chunkSize = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('id', $value)) {
            $this->_usedProperties['id'] = true;
            $this->id = $value['id'];
            unset($value['id']);
        }

        if (array_key_exists('hostname', $value)) {
            $this->_usedProperties['hostname'] = true;
            $this->hostname = $value['hostname'];
            unset($value['hostname']);
        }

        if (array_key_exists('port', $value)) {
            $this->_usedProperties['port'] = true;
            $this->port = $value['port'];
            unset($value['port']);
        }

        if (array_key_exists('chunk_size', $value)) {
            $this->_usedProperties['chunkSize'] = true;
            $this->chunkSize = $value['chunk_size'];
            unset($value['chunk_size']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['id'])) {
            $output['id'] = $this->id;
        }
        if (isset($this->_usedProperties['hostname'])) {
            $output['hostname'] = $this->hostname;
        }
        if (isset($this->_usedProperties['port'])) {
            $output['port'] = $this->port;
        }
        if (isset($this->_usedProperties['chunkSize'])) {
            $output['chunk_size'] = $this->chunkSize;
        }

        return $output;
    }

}
