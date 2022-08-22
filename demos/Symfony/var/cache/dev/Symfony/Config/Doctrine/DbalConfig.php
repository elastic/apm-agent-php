<?php

namespace Symfony\Config\Doctrine;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Dbal'.\DIRECTORY_SEPARATOR.'TypeConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Dbal'.\DIRECTORY_SEPARATOR.'ConnectionConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DbalConfig 
{
    private $defaultConnection;
    private $types;
    private $connections;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultConnection($value): static
    {
        $this->_usedProperties['defaultConnection'] = true;
        $this->defaultConnection = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Doctrine\Dbal\TypeConfig|$this
     */
    public function type(string $name, mixed $value = []): \Symfony\Config\Doctrine\Dbal\TypeConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['types'] = true;
            $this->types[$name] = $value;

            return $this;
        }

        if (!isset($this->types[$name]) || !$this->types[$name] instanceof \Symfony\Config\Doctrine\Dbal\TypeConfig) {
            $this->_usedProperties['types'] = true;
            $this->types[$name] = new \Symfony\Config\Doctrine\Dbal\TypeConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "type()" has already been initialized. You cannot pass values the second time you call type().');
        }

        return $this->types[$name];
    }

    /**
     * @return \Symfony\Config\Doctrine\Dbal\ConnectionConfig|$this
     */
    public function connection(string $name, mixed $value = []): \Symfony\Config\Doctrine\Dbal\ConnectionConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['connections'] = true;
            $this->connections[$name] = $value;

            return $this;
        }

        if (!isset($this->connections[$name]) || !$this->connections[$name] instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig) {
            $this->_usedProperties['connections'] = true;
            $this->connections[$name] = new \Symfony\Config\Doctrine\Dbal\ConnectionConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "connection()" has already been initialized. You cannot pass values the second time you call connection().');
        }

        return $this->connections[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('default_connection', $value)) {
            $this->_usedProperties['defaultConnection'] = true;
            $this->defaultConnection = $value['default_connection'];
            unset($value['default_connection']);
        }

        if (array_key_exists('types', $value)) {
            $this->_usedProperties['types'] = true;
            $this->types = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Dbal\TypeConfig($v) : $v; }, $value['types']);
            unset($value['types']);
        }

        if (array_key_exists('connections', $value)) {
            $this->_usedProperties['connections'] = true;
            $this->connections = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Dbal\ConnectionConfig($v) : $v; }, $value['connections']);
            unset($value['connections']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['defaultConnection'])) {
            $output['default_connection'] = $this->defaultConnection;
        }
        if (isset($this->_usedProperties['types'])) {
            $output['types'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Dbal\TypeConfig ? $v->toArray() : $v; }, $this->types);
        }
        if (isset($this->_usedProperties['connections'])) {
            $output['connections'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig ? $v->toArray() : $v; }, $this->connections);
        }

        return $output;
    }

}
