<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Cache'.\DIRECTORY_SEPARATOR.'PoolConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class CacheConfig 
{
    private $prefixSeed;
    private $app;
    private $system;
    private $directory;
    private $defaultPsr6Provider;
    private $defaultRedisProvider;
    private $defaultMemcachedProvider;
    private $defaultDoctrineDbalProvider;
    private $defaultPdoProvider;
    private $pools;
    private $_usedProperties = [];

    /**
     * Used to namespace cache keys when using several apps with the same shared backend
     * @example my-application-name/%kernel.environment%
     * @default '_%kernel.project_dir%.%kernel.container_class%'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function prefixSeed($value): static
    {
        $this->_usedProperties['prefixSeed'] = true;
        $this->prefixSeed = $value;

        return $this;
    }

    /**
     * App related cache pools configuration
     * @default 'cache.adapter.filesystem'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function app($value): static
    {
        $this->_usedProperties['app'] = true;
        $this->app = $value;

        return $this;
    }

    /**
     * System related cache pools configuration
     * @default 'cache.adapter.system'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function system($value): static
    {
        $this->_usedProperties['system'] = true;
        $this->system = $value;

        return $this;
    }

    /**
     * @default '%kernel.cache_dir%/pools/app'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function directory($value): static
    {
        $this->_usedProperties['directory'] = true;
        $this->directory = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultPsr6Provider($value): static
    {
        $this->_usedProperties['defaultPsr6Provider'] = true;
        $this->defaultPsr6Provider = $value;

        return $this;
    }

    /**
     * @default 'redis://localhost'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultRedisProvider($value): static
    {
        $this->_usedProperties['defaultRedisProvider'] = true;
        $this->defaultRedisProvider = $value;

        return $this;
    }

    /**
     * @default 'memcached://localhost'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultMemcachedProvider($value): static
    {
        $this->_usedProperties['defaultMemcachedProvider'] = true;
        $this->defaultMemcachedProvider = $value;

        return $this;
    }

    /**
     * @default 'database_connection'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultDoctrineDbalProvider($value): static
    {
        $this->_usedProperties['defaultDoctrineDbalProvider'] = true;
        $this->defaultDoctrineDbalProvider = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultPdoProvider($value): static
    {
        $this->_usedProperties['defaultPdoProvider'] = true;
        $this->defaultPdoProvider = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Framework\Cache\PoolConfig|$this
     */
    public function pool(string $name, mixed $value = []): \Symfony\Config\Framework\Cache\PoolConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['pools'] = true;
            $this->pools[$name] = $value;

            return $this;
        }

        if (!isset($this->pools[$name]) || !$this->pools[$name] instanceof \Symfony\Config\Framework\Cache\PoolConfig) {
            $this->_usedProperties['pools'] = true;
            $this->pools[$name] = new \Symfony\Config\Framework\Cache\PoolConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "pool()" has already been initialized. You cannot pass values the second time you call pool().');
        }

        return $this->pools[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('prefix_seed', $value)) {
            $this->_usedProperties['prefixSeed'] = true;
            $this->prefixSeed = $value['prefix_seed'];
            unset($value['prefix_seed']);
        }

        if (array_key_exists('app', $value)) {
            $this->_usedProperties['app'] = true;
            $this->app = $value['app'];
            unset($value['app']);
        }

        if (array_key_exists('system', $value)) {
            $this->_usedProperties['system'] = true;
            $this->system = $value['system'];
            unset($value['system']);
        }

        if (array_key_exists('directory', $value)) {
            $this->_usedProperties['directory'] = true;
            $this->directory = $value['directory'];
            unset($value['directory']);
        }

        if (array_key_exists('default_psr6_provider', $value)) {
            $this->_usedProperties['defaultPsr6Provider'] = true;
            $this->defaultPsr6Provider = $value['default_psr6_provider'];
            unset($value['default_psr6_provider']);
        }

        if (array_key_exists('default_redis_provider', $value)) {
            $this->_usedProperties['defaultRedisProvider'] = true;
            $this->defaultRedisProvider = $value['default_redis_provider'];
            unset($value['default_redis_provider']);
        }

        if (array_key_exists('default_memcached_provider', $value)) {
            $this->_usedProperties['defaultMemcachedProvider'] = true;
            $this->defaultMemcachedProvider = $value['default_memcached_provider'];
            unset($value['default_memcached_provider']);
        }

        if (array_key_exists('default_doctrine_dbal_provider', $value)) {
            $this->_usedProperties['defaultDoctrineDbalProvider'] = true;
            $this->defaultDoctrineDbalProvider = $value['default_doctrine_dbal_provider'];
            unset($value['default_doctrine_dbal_provider']);
        }

        if (array_key_exists('default_pdo_provider', $value)) {
            $this->_usedProperties['defaultPdoProvider'] = true;
            $this->defaultPdoProvider = $value['default_pdo_provider'];
            unset($value['default_pdo_provider']);
        }

        if (array_key_exists('pools', $value)) {
            $this->_usedProperties['pools'] = true;
            $this->pools = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Cache\PoolConfig($v) : $v; }, $value['pools']);
            unset($value['pools']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['prefixSeed'])) {
            $output['prefix_seed'] = $this->prefixSeed;
        }
        if (isset($this->_usedProperties['app'])) {
            $output['app'] = $this->app;
        }
        if (isset($this->_usedProperties['system'])) {
            $output['system'] = $this->system;
        }
        if (isset($this->_usedProperties['directory'])) {
            $output['directory'] = $this->directory;
        }
        if (isset($this->_usedProperties['defaultPsr6Provider'])) {
            $output['default_psr6_provider'] = $this->defaultPsr6Provider;
        }
        if (isset($this->_usedProperties['defaultRedisProvider'])) {
            $output['default_redis_provider'] = $this->defaultRedisProvider;
        }
        if (isset($this->_usedProperties['defaultMemcachedProvider'])) {
            $output['default_memcached_provider'] = $this->defaultMemcachedProvider;
        }
        if (isset($this->_usedProperties['defaultDoctrineDbalProvider'])) {
            $output['default_doctrine_dbal_provider'] = $this->defaultDoctrineDbalProvider;
        }
        if (isset($this->_usedProperties['defaultPdoProvider'])) {
            $output['default_pdo_provider'] = $this->defaultPdoProvider;
        }
        if (isset($this->_usedProperties['pools'])) {
            $output['pools'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Cache\PoolConfig ? $v->toArray() : $v; }, $this->pools);
        }

        return $output;
    }

}
