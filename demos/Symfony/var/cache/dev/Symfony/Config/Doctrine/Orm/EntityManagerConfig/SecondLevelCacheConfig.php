<?php

namespace Symfony\Config\Doctrine\Orm\EntityManagerConfig;

require_once __DIR__.\DIRECTORY_SEPARATOR.'SecondLevelCache'.\DIRECTORY_SEPARATOR.'RegionCacheDriverConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SecondLevelCache'.\DIRECTORY_SEPARATOR.'RegionConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SecondLevelCache'.\DIRECTORY_SEPARATOR.'LoggerConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class SecondLevelCacheConfig 
{
    private $regionCacheDriver;
    private $regionLockLifetime;
    private $logEnabled;
    private $regionLifetime;
    private $enabled;
    private $factory;
    private $regions;
    private $loggers;
    private $_usedProperties = [];

    /**
     * @default {"type":null}
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig|$this
     */
    public function regionCacheDriver(mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['regionCacheDriver'] = true;
            $this->regionCacheDriver = $value;

            return $this;
        }

        if (!$this->regionCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig) {
            $this->_usedProperties['regionCacheDriver'] = true;
            $this->regionCacheDriver = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "regionCacheDriver()" has already been initialized. You cannot pass values the second time you call regionCacheDriver().');
        }

        return $this->regionCacheDriver;
    }

    /**
     * @default 60
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function regionLockLifetime($value): static
    {
        $this->_usedProperties['regionLockLifetime'] = true;
        $this->regionLockLifetime = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function logEnabled($value): static
    {
        $this->_usedProperties['logEnabled'] = true;
        $this->logEnabled = $value;

        return $this;
    }

    /**
     * @default 3600
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function regionLifetime($value): static
    {
        $this->_usedProperties['regionLifetime'] = true;
        $this->regionLifetime = $value;

        return $this;
    }

    /**
     * @default true
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function factory($value): static
    {
        $this->_usedProperties['factory'] = true;
        $this->factory = $value;

        return $this;
    }

    public function region(string $name, array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionConfig
    {
        if (!isset($this->regions[$name])) {
            $this->_usedProperties['regions'] = true;
            $this->regions[$name] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "region()" has already been initialized. You cannot pass values the second time you call region().');
        }

        return $this->regions[$name];
    }

    public function logger(string $name, array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\LoggerConfig
    {
        if (!isset($this->loggers[$name])) {
            $this->_usedProperties['loggers'] = true;
            $this->loggers[$name] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\LoggerConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "logger()" has already been initialized. You cannot pass values the second time you call logger().');
        }

        return $this->loggers[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('region_cache_driver', $value)) {
            $this->_usedProperties['regionCacheDriver'] = true;
            $this->regionCacheDriver = \is_array($value['region_cache_driver']) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig($value['region_cache_driver']) : $value['region_cache_driver'];
            unset($value['region_cache_driver']);
        }

        if (array_key_exists('region_lock_lifetime', $value)) {
            $this->_usedProperties['regionLockLifetime'] = true;
            $this->regionLockLifetime = $value['region_lock_lifetime'];
            unset($value['region_lock_lifetime']);
        }

        if (array_key_exists('log_enabled', $value)) {
            $this->_usedProperties['logEnabled'] = true;
            $this->logEnabled = $value['log_enabled'];
            unset($value['log_enabled']);
        }

        if (array_key_exists('region_lifetime', $value)) {
            $this->_usedProperties['regionLifetime'] = true;
            $this->regionLifetime = $value['region_lifetime'];
            unset($value['region_lifetime']);
        }

        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('factory', $value)) {
            $this->_usedProperties['factory'] = true;
            $this->factory = $value['factory'];
            unset($value['factory']);
        }

        if (array_key_exists('regions', $value)) {
            $this->_usedProperties['regions'] = true;
            $this->regions = array_map(function ($v) { return new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionConfig($v); }, $value['regions']);
            unset($value['regions']);
        }

        if (array_key_exists('loggers', $value)) {
            $this->_usedProperties['loggers'] = true;
            $this->loggers = array_map(function ($v) { return new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\LoggerConfig($v); }, $value['loggers']);
            unset($value['loggers']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['regionCacheDriver'])) {
            $output['region_cache_driver'] = $this->regionCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCache\RegionCacheDriverConfig ? $this->regionCacheDriver->toArray() : $this->regionCacheDriver;
        }
        if (isset($this->_usedProperties['regionLockLifetime'])) {
            $output['region_lock_lifetime'] = $this->regionLockLifetime;
        }
        if (isset($this->_usedProperties['logEnabled'])) {
            $output['log_enabled'] = $this->logEnabled;
        }
        if (isset($this->_usedProperties['regionLifetime'])) {
            $output['region_lifetime'] = $this->regionLifetime;
        }
        if (isset($this->_usedProperties['enabled'])) {
            $output['enabled'] = $this->enabled;
        }
        if (isset($this->_usedProperties['factory'])) {
            $output['factory'] = $this->factory;
        }
        if (isset($this->_usedProperties['regions'])) {
            $output['regions'] = array_map(function ($v) { return $v->toArray(); }, $this->regions);
        }
        if (isset($this->_usedProperties['loggers'])) {
            $output['loggers'] = array_map(function ($v) { return $v->toArray(); }, $this->loggers);
        }

        return $output;
    }

}
