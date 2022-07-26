<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'DoctrineMigrations'.\DIRECTORY_SEPARATOR.'StorageConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DoctrineMigrationsConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $migrationsPaths;
    private $services;
    private $factories;
    private $storage;
    private $migrations;
    private $connection;
    private $em;
    private $allOrNothing;
    private $checkDatabasePlatform;
    private $customTemplate;
    private $organizeMigrations;
    private $enableProfiler;
    private $transactional;
    private $_usedProperties = [];

    /**
     * @return $this
     */
    public function migrationsPath(string $namespace, mixed $value): static
    {
        $this->_usedProperties['migrationsPaths'] = true;
        $this->migrationsPaths[$namespace] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function services(string $service, mixed $value): static
    {
        $this->_usedProperties['services'] = true;
        $this->services[$service] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function factories(string $factory, mixed $value): static
    {
        $this->_usedProperties['factories'] = true;
        $this->factories[$factory] = $value;

        return $this;
    }

    /**
     * Storage to use for migration status metadata.
     * @default {"table_storage":{"table_name":null,"version_column_name":null,"version_column_length":null,"executed_at_column_name":null,"execution_time_column_name":null}}
    */
    public function storage(array $value = []): \Symfony\Config\DoctrineMigrations\StorageConfig
    {
        if (null === $this->storage) {
            $this->_usedProperties['storage'] = true;
            $this->storage = new \Symfony\Config\DoctrineMigrations\StorageConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "storage()" has already been initialized. You cannot pass values the second time you call storage().');
        }

        return $this->storage;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function migrations(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['migrations'] = true;
        $this->migrations = $value;

        return $this;
    }

    /**
     * Connection name to use for the migrations database.
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
     * Entity manager name to use for the migrations database (available when doctrine/orm is installed).
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function em($value): static
    {
        $this->_usedProperties['em'] = true;
        $this->em = $value;

        return $this;
    }

    /**
     * Run all migrations in a transaction.
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function allOrNothing($value): static
    {
        $this->_usedProperties['allOrNothing'] = true;
        $this->allOrNothing = $value;

        return $this;
    }

    /**
     * Adds an extra check in the generated migrations to allow execution only on the same platform as they were initially generated on.
     * @default true
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function checkDatabasePlatform($value): static
    {
        $this->_usedProperties['checkDatabasePlatform'] = true;
        $this->checkDatabasePlatform = $value;

        return $this;
    }

    /**
     * Custom template path for generated migration classes.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function customTemplate($value): static
    {
        $this->_usedProperties['customTemplate'] = true;
        $this->customTemplate = $value;

        return $this;
    }

    /**
     * Organize migrations mode. Possible values are: "BY_YEAR", "BY_YEAR_AND_MONTH", false
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function organizeMigrations($value): static
    {
        $this->_usedProperties['organizeMigrations'] = true;
        $this->organizeMigrations = $value;

        return $this;
    }

    /**
     * Use profiler to calculate and visualize migration status.
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enableProfiler($value): static
    {
        $this->_usedProperties['enableProfiler'] = true;
        $this->enableProfiler = $value;

        return $this;
    }

    /**
     * Whether or not to wrap migrations in a single transaction.
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function transactional($value): static
    {
        $this->_usedProperties['transactional'] = true;
        $this->transactional = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'doctrine_migrations';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('migrations_paths', $value)) {
            $this->_usedProperties['migrationsPaths'] = true;
            $this->migrationsPaths = $value['migrations_paths'];
            unset($value['migrations_paths']);
        }

        if (array_key_exists('services', $value)) {
            $this->_usedProperties['services'] = true;
            $this->services = $value['services'];
            unset($value['services']);
        }

        if (array_key_exists('factories', $value)) {
            $this->_usedProperties['factories'] = true;
            $this->factories = $value['factories'];
            unset($value['factories']);
        }

        if (array_key_exists('storage', $value)) {
            $this->_usedProperties['storage'] = true;
            $this->storage = new \Symfony\Config\DoctrineMigrations\StorageConfig($value['storage']);
            unset($value['storage']);
        }

        if (array_key_exists('migrations', $value)) {
            $this->_usedProperties['migrations'] = true;
            $this->migrations = $value['migrations'];
            unset($value['migrations']);
        }

        if (array_key_exists('connection', $value)) {
            $this->_usedProperties['connection'] = true;
            $this->connection = $value['connection'];
            unset($value['connection']);
        }

        if (array_key_exists('em', $value)) {
            $this->_usedProperties['em'] = true;
            $this->em = $value['em'];
            unset($value['em']);
        }

        if (array_key_exists('all_or_nothing', $value)) {
            $this->_usedProperties['allOrNothing'] = true;
            $this->allOrNothing = $value['all_or_nothing'];
            unset($value['all_or_nothing']);
        }

        if (array_key_exists('check_database_platform', $value)) {
            $this->_usedProperties['checkDatabasePlatform'] = true;
            $this->checkDatabasePlatform = $value['check_database_platform'];
            unset($value['check_database_platform']);
        }

        if (array_key_exists('custom_template', $value)) {
            $this->_usedProperties['customTemplate'] = true;
            $this->customTemplate = $value['custom_template'];
            unset($value['custom_template']);
        }

        if (array_key_exists('organize_migrations', $value)) {
            $this->_usedProperties['organizeMigrations'] = true;
            $this->organizeMigrations = $value['organize_migrations'];
            unset($value['organize_migrations']);
        }

        if (array_key_exists('enable_profiler', $value)) {
            $this->_usedProperties['enableProfiler'] = true;
            $this->enableProfiler = $value['enable_profiler'];
            unset($value['enable_profiler']);
        }

        if (array_key_exists('transactional', $value)) {
            $this->_usedProperties['transactional'] = true;
            $this->transactional = $value['transactional'];
            unset($value['transactional']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['migrationsPaths'])) {
            $output['migrations_paths'] = $this->migrationsPaths;
        }
        if (isset($this->_usedProperties['services'])) {
            $output['services'] = $this->services;
        }
        if (isset($this->_usedProperties['factories'])) {
            $output['factories'] = $this->factories;
        }
        if (isset($this->_usedProperties['storage'])) {
            $output['storage'] = $this->storage->toArray();
        }
        if (isset($this->_usedProperties['migrations'])) {
            $output['migrations'] = $this->migrations;
        }
        if (isset($this->_usedProperties['connection'])) {
            $output['connection'] = $this->connection;
        }
        if (isset($this->_usedProperties['em'])) {
            $output['em'] = $this->em;
        }
        if (isset($this->_usedProperties['allOrNothing'])) {
            $output['all_or_nothing'] = $this->allOrNothing;
        }
        if (isset($this->_usedProperties['checkDatabasePlatform'])) {
            $output['check_database_platform'] = $this->checkDatabasePlatform;
        }
        if (isset($this->_usedProperties['customTemplate'])) {
            $output['custom_template'] = $this->customTemplate;
        }
        if (isset($this->_usedProperties['organizeMigrations'])) {
            $output['organize_migrations'] = $this->organizeMigrations;
        }
        if (isset($this->_usedProperties['enableProfiler'])) {
            $output['enable_profiler'] = $this->enableProfiler;
        }
        if (isset($this->_usedProperties['transactional'])) {
            $output['transactional'] = $this->transactional;
        }

        return $output;
    }

}
