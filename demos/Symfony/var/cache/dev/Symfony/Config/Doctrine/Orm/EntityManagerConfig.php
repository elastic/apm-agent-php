<?php

namespace Symfony\Config\Doctrine\Orm;

require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'QueryCacheDriverConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'MetadataCacheDriverConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'ResultCacheDriverConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'EntityListenersConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'SecondLevelCacheConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'MappingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'DqlConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'EntityManagerConfig'.\DIRECTORY_SEPARATOR.'FilterConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\ParamConfigurator;

/**
 * This class is automatically generated to help in creating a config.
 */
class EntityManagerConfig 
{
    private $queryCacheDriver;
    private $metadataCacheDriver;
    private $resultCacheDriver;
    private $entityListeners;
    private $connection;
    private $classMetadataFactoryName;
    private $defaultRepositoryClass;
    private $autoMapping;
    private $namingStrategy;
    private $quoteStrategy;
    private $entityListenerResolver;
    private $repositoryFactory;
    private $schemaIgnoreClasses;
    private $secondLevelCache;
    private $hydrators;
    private $mappings;
    private $dql;
    private $filters;
    private $_usedProperties = [];

    /**
     * @default {"type":null}
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig|$this
     */
    public function queryCacheDriver(mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['queryCacheDriver'] = true;
            $this->queryCacheDriver = $value;

            return $this;
        }

        if (!$this->queryCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig) {
            $this->_usedProperties['queryCacheDriver'] = true;
            $this->queryCacheDriver = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "queryCacheDriver()" has already been initialized. You cannot pass values the second time you call queryCacheDriver().');
        }

        return $this->queryCacheDriver;
    }

    /**
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig|$this
     */
    public function metadataCacheDriver(mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['metadataCacheDriver'] = true;
            $this->metadataCacheDriver = $value;

            return $this;
        }

        if (!$this->metadataCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig) {
            $this->_usedProperties['metadataCacheDriver'] = true;
            $this->metadataCacheDriver = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "metadataCacheDriver()" has already been initialized. You cannot pass values the second time you call metadataCacheDriver().');
        }

        return $this->metadataCacheDriver;
    }

    /**
     * @default {"type":null}
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig|$this
     */
    public function resultCacheDriver(mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['resultCacheDriver'] = true;
            $this->resultCacheDriver = $value;

            return $this;
        }

        if (!$this->resultCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig) {
            $this->_usedProperties['resultCacheDriver'] = true;
            $this->resultCacheDriver = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "resultCacheDriver()" has already been initialized. You cannot pass values the second time you call resultCacheDriver().');
        }

        return $this->resultCacheDriver;
    }

    /**
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig|$this
     */
    public function entityListeners(mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['entityListeners'] = true;
            $this->entityListeners = $value;

            return $this;
        }

        if (!$this->entityListeners instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig) {
            $this->_usedProperties['entityListeners'] = true;
            $this->entityListeners = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "entityListeners()" has already been initialized. You cannot pass values the second time you call entityListeners().');
        }

        return $this->entityListeners;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function connection($value): static
    {
        $this->_usedProperties['connection'] = true;
        $this->connection = $value;

        return $this;
    }

    /**
     * @default 'Doctrine\\ORM\\Mapping\\ClassMetadataFactory'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function classMetadataFactoryName($value): static
    {
        $this->_usedProperties['classMetadataFactoryName'] = true;
        $this->classMetadataFactoryName = $value;

        return $this;
    }

    /**
     * @default 'Doctrine\\ORM\\EntityRepository'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultRepositoryClass($value): static
    {
        $this->_usedProperties['defaultRepositoryClass'] = true;
        $this->defaultRepositoryClass = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function autoMapping($value): static
    {
        $this->_usedProperties['autoMapping'] = true;
        $this->autoMapping = $value;

        return $this;
    }

    /**
     * @default 'doctrine.orm.naming_strategy.default'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function namingStrategy($value): static
    {
        $this->_usedProperties['namingStrategy'] = true;
        $this->namingStrategy = $value;

        return $this;
    }

    /**
     * @default 'doctrine.orm.quote_strategy.default'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function quoteStrategy($value): static
    {
        $this->_usedProperties['quoteStrategy'] = true;
        $this->quoteStrategy = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function entityListenerResolver($value): static
    {
        $this->_usedProperties['entityListenerResolver'] = true;
        $this->entityListenerResolver = $value;

        return $this;
    }

    /**
     * @default 'doctrine.orm.container_repository_factory'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function repositoryFactory($value): static
    {
        $this->_usedProperties['repositoryFactory'] = true;
        $this->repositoryFactory = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function schemaIgnoreClasses(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['schemaIgnoreClasses'] = true;
        $this->schemaIgnoreClasses = $value;

        return $this;
    }

    public function secondLevelCache(array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCacheConfig
    {
        if (null === $this->secondLevelCache) {
            $this->_usedProperties['secondLevelCache'] = true;
            $this->secondLevelCache = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCacheConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "secondLevelCache()" has already been initialized. You cannot pass values the second time you call secondLevelCache().');
        }

        return $this->secondLevelCache;
    }

    /**
     * @return $this
     */
    public function hydrator(string $name, mixed $value): static
    {
        $this->_usedProperties['hydrators'] = true;
        $this->hydrators[$name] = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig|$this
     */
    public function mapping(string $name, mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['mappings'] = true;
            $this->mappings[$name] = $value;

            return $this;
        }

        if (!isset($this->mappings[$name]) || !$this->mappings[$name] instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig) {
            $this->_usedProperties['mappings'] = true;
            $this->mappings[$name] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "mapping()" has already been initialized. You cannot pass values the second time you call mapping().');
        }

        return $this->mappings[$name];
    }

    public function dql(array $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\DqlConfig
    {
        if (null === $this->dql) {
            $this->_usedProperties['dql'] = true;
            $this->dql = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\DqlConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "dql()" has already been initialized. You cannot pass values the second time you call dql().');
        }

        return $this->dql;
    }

    /**
     * Register SQL Filters in the entity manager
     * @return \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig|$this
     */
    public function filter(string $name, mixed $value = []): \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['filters'] = true;
            $this->filters[$name] = $value;

            return $this;
        }

        if (!isset($this->filters[$name]) || !$this->filters[$name] instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig) {
            $this->_usedProperties['filters'] = true;
            $this->filters[$name] = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "filter()" has already been initialized. You cannot pass values the second time you call filter().');
        }

        return $this->filters[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('query_cache_driver', $value)) {
            $this->_usedProperties['queryCacheDriver'] = true;
            $this->queryCacheDriver = \is_array($value['query_cache_driver']) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig($value['query_cache_driver']) : $value['query_cache_driver'];
            unset($value['query_cache_driver']);
        }

        if (array_key_exists('metadata_cache_driver', $value)) {
            $this->_usedProperties['metadataCacheDriver'] = true;
            $this->metadataCacheDriver = \is_array($value['metadata_cache_driver']) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig($value['metadata_cache_driver']) : $value['metadata_cache_driver'];
            unset($value['metadata_cache_driver']);
        }

        if (array_key_exists('result_cache_driver', $value)) {
            $this->_usedProperties['resultCacheDriver'] = true;
            $this->resultCacheDriver = \is_array($value['result_cache_driver']) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig($value['result_cache_driver']) : $value['result_cache_driver'];
            unset($value['result_cache_driver']);
        }

        if (array_key_exists('entity_listeners', $value)) {
            $this->_usedProperties['entityListeners'] = true;
            $this->entityListeners = \is_array($value['entity_listeners']) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig($value['entity_listeners']) : $value['entity_listeners'];
            unset($value['entity_listeners']);
        }

        if (array_key_exists('connection', $value)) {
            $this->_usedProperties['connection'] = true;
            $this->connection = $value['connection'];
            unset($value['connection']);
        }

        if (array_key_exists('class_metadata_factory_name', $value)) {
            $this->_usedProperties['classMetadataFactoryName'] = true;
            $this->classMetadataFactoryName = $value['class_metadata_factory_name'];
            unset($value['class_metadata_factory_name']);
        }

        if (array_key_exists('default_repository_class', $value)) {
            $this->_usedProperties['defaultRepositoryClass'] = true;
            $this->defaultRepositoryClass = $value['default_repository_class'];
            unset($value['default_repository_class']);
        }

        if (array_key_exists('auto_mapping', $value)) {
            $this->_usedProperties['autoMapping'] = true;
            $this->autoMapping = $value['auto_mapping'];
            unset($value['auto_mapping']);
        }

        if (array_key_exists('naming_strategy', $value)) {
            $this->_usedProperties['namingStrategy'] = true;
            $this->namingStrategy = $value['naming_strategy'];
            unset($value['naming_strategy']);
        }

        if (array_key_exists('quote_strategy', $value)) {
            $this->_usedProperties['quoteStrategy'] = true;
            $this->quoteStrategy = $value['quote_strategy'];
            unset($value['quote_strategy']);
        }

        if (array_key_exists('entity_listener_resolver', $value)) {
            $this->_usedProperties['entityListenerResolver'] = true;
            $this->entityListenerResolver = $value['entity_listener_resolver'];
            unset($value['entity_listener_resolver']);
        }

        if (array_key_exists('repository_factory', $value)) {
            $this->_usedProperties['repositoryFactory'] = true;
            $this->repositoryFactory = $value['repository_factory'];
            unset($value['repository_factory']);
        }

        if (array_key_exists('schema_ignore_classes', $value)) {
            $this->_usedProperties['schemaIgnoreClasses'] = true;
            $this->schemaIgnoreClasses = $value['schema_ignore_classes'];
            unset($value['schema_ignore_classes']);
        }

        if (array_key_exists('second_level_cache', $value)) {
            $this->_usedProperties['secondLevelCache'] = true;
            $this->secondLevelCache = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\SecondLevelCacheConfig($value['second_level_cache']);
            unset($value['second_level_cache']);
        }

        if (array_key_exists('hydrators', $value)) {
            $this->_usedProperties['hydrators'] = true;
            $this->hydrators = $value['hydrators'];
            unset($value['hydrators']);
        }

        if (array_key_exists('mappings', $value)) {
            $this->_usedProperties['mappings'] = true;
            $this->mappings = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig($v) : $v; }, $value['mappings']);
            unset($value['mappings']);
        }

        if (array_key_exists('dql', $value)) {
            $this->_usedProperties['dql'] = true;
            $this->dql = new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\DqlConfig($value['dql']);
            unset($value['dql']);
        }

        if (array_key_exists('filters', $value)) {
            $this->_usedProperties['filters'] = true;
            $this->filters = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig($v) : $v; }, $value['filters']);
            unset($value['filters']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['queryCacheDriver'])) {
            $output['query_cache_driver'] = $this->queryCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\QueryCacheDriverConfig ? $this->queryCacheDriver->toArray() : $this->queryCacheDriver;
        }
        if (isset($this->_usedProperties['metadataCacheDriver'])) {
            $output['metadata_cache_driver'] = $this->metadataCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MetadataCacheDriverConfig ? $this->metadataCacheDriver->toArray() : $this->metadataCacheDriver;
        }
        if (isset($this->_usedProperties['resultCacheDriver'])) {
            $output['result_cache_driver'] = $this->resultCacheDriver instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\ResultCacheDriverConfig ? $this->resultCacheDriver->toArray() : $this->resultCacheDriver;
        }
        if (isset($this->_usedProperties['entityListeners'])) {
            $output['entity_listeners'] = $this->entityListeners instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\EntityListenersConfig ? $this->entityListeners->toArray() : $this->entityListeners;
        }
        if (isset($this->_usedProperties['connection'])) {
            $output['connection'] = $this->connection;
        }
        if (isset($this->_usedProperties['classMetadataFactoryName'])) {
            $output['class_metadata_factory_name'] = $this->classMetadataFactoryName;
        }
        if (isset($this->_usedProperties['defaultRepositoryClass'])) {
            $output['default_repository_class'] = $this->defaultRepositoryClass;
        }
        if (isset($this->_usedProperties['autoMapping'])) {
            $output['auto_mapping'] = $this->autoMapping;
        }
        if (isset($this->_usedProperties['namingStrategy'])) {
            $output['naming_strategy'] = $this->namingStrategy;
        }
        if (isset($this->_usedProperties['quoteStrategy'])) {
            $output['quote_strategy'] = $this->quoteStrategy;
        }
        if (isset($this->_usedProperties['entityListenerResolver'])) {
            $output['entity_listener_resolver'] = $this->entityListenerResolver;
        }
        if (isset($this->_usedProperties['repositoryFactory'])) {
            $output['repository_factory'] = $this->repositoryFactory;
        }
        if (isset($this->_usedProperties['schemaIgnoreClasses'])) {
            $output['schema_ignore_classes'] = $this->schemaIgnoreClasses;
        }
        if (isset($this->_usedProperties['secondLevelCache'])) {
            $output['second_level_cache'] = $this->secondLevelCache->toArray();
        }
        if (isset($this->_usedProperties['hydrators'])) {
            $output['hydrators'] = $this->hydrators;
        }
        if (isset($this->_usedProperties['mappings'])) {
            $output['mappings'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\MappingConfig ? $v->toArray() : $v; }, $this->mappings);
        }
        if (isset($this->_usedProperties['dql'])) {
            $output['dql'] = $this->dql->toArray();
        }
        if (isset($this->_usedProperties['filters'])) {
            $output['filters'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Orm\EntityManagerConfig\FilterConfig ? $v->toArray() : $v; }, $this->filters);
        }

        return $output;
    }

}
