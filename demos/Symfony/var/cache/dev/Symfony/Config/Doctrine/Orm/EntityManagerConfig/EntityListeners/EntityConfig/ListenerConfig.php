<?php

namespace Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig;

require_once __DIR__.\DIRECTORY_SEPARATOR.'ListenerConfig'.\DIRECTORY_SEPARATOR.'EventConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ListenerConfig 
{
    private $events;
    private $_usedProperties = [];

    public function event(array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig\EventConfig
    {
        $this->_usedProperties['events'] = true;

        return $this->events[] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig\EventConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('events', $value)) {
            $this->_usedProperties['events'] = true;
            $this->events = array_map(function ($v) { return new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListeners\EntityConfig\ListenerConfig\EventConfig($v); }, $value['events']);
            unset($value['events']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['events'])) {
            $output['events'] = array_map(function ($v) { return $v->toArray(); }, $this->events);
        }

        return $output;
    }

}
