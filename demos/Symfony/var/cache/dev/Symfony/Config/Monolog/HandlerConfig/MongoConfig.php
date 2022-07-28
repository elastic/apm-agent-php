<?php

namespace Symfony\Config\Monolog\HandlerConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MongoConfig 
{
    private $id;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $database;
    private $collection;
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
    public function host($value): static
    {
        $this->_usedProperties['host'] = true;
        $this->host = $value;

        return $this;
    }

    /**
     * @default 27017
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function user($value): static
    {
        $this->_usedProperties['user'] = true;
        $this->user = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function pass($value): static
    {
        $this->_usedProperties['pass'] = true;
        $this->pass = $value;

        return $this;
    }

    /**
     * @default 'monolog'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function database($value): static
    {
        $this->_usedProperties['database'] = true;
        $this->database = $value;

        return $this;
    }

    /**
     * @default 'logs'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function collection($value): static
    {
        $this->_usedProperties['collection'] = true;
        $this->collection = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('id', $value)) {
            $this->_usedProperties['id'] = true;
            $this->id = $value['id'];
            unset($value['id']);
        }

        if (array_key_exists('host', $value)) {
            $this->_usedProperties['host'] = true;
            $this->host = $value['host'];
            unset($value['host']);
        }

        if (array_key_exists('port', $value)) {
            $this->_usedProperties['port'] = true;
            $this->port = $value['port'];
            unset($value['port']);
        }

        if (array_key_exists('user', $value)) {
            $this->_usedProperties['user'] = true;
            $this->user = $value['user'];
            unset($value['user']);
        }

        if (array_key_exists('pass', $value)) {
            $this->_usedProperties['pass'] = true;
            $this->pass = $value['pass'];
            unset($value['pass']);
        }

        if (array_key_exists('database', $value)) {
            $this->_usedProperties['database'] = true;
            $this->database = $value['database'];
            unset($value['database']);
        }

        if (array_key_exists('collection', $value)) {
            $this->_usedProperties['collection'] = true;
            $this->collection = $value['collection'];
            unset($value['collection']);
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
        if (isset($this->_usedProperties['host'])) {
            $output['host'] = $this->host;
        }
        if (isset($this->_usedProperties['port'])) {
            $output['port'] = $this->port;
        }
        if (isset($this->_usedProperties['user'])) {
            $output['user'] = $this->user;
        }
        if (isset($this->_usedProperties['pass'])) {
            $output['pass'] = $this->pass;
        }
        if (isset($this->_usedProperties['database'])) {
            $output['database'] = $this->database;
        }
        if (isset($this->_usedProperties['collection'])) {
            $output['collection'] = $this->collection;
        }

        return $output;
    }

}
