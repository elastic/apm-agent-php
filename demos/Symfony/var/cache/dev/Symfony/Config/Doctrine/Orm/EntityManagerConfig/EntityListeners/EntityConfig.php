<?php

namespace Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners;

require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityConfig'.\DIRECTORY_SEPARATOR.'ListenerConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class EntityConfig 
{
    private $listeners;
    private $_usedProperties = [];

    public function listener(string $class, array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig
    {
        if (!isset($this->listeners[$class])) {
            $this->_usedProperties['listeners'] = true;
            $this->listeners[$class] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "listener()" has already been initialized. You cannot pass values the second time you call listener().');
        }

        return $this->listeners[$class];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('listeners', $value)) {
            $this->_usedProperties['listeners'] = true;
            $this->listeners = array_map(function ($v) { return new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig($v); }, $value['listeners']);
            unset($value['listeners']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['listeners'])) {
            $output['listeners'] = array_map(function ($v) { return $v->toArray(); }, $this->listeners);
        }

        return $output;
    }

}
