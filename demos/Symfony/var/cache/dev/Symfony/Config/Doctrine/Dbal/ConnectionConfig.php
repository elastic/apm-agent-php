<?php

namespace Symfony\Config\Doctrine\Dbal;

require_once __DIR__.\DIRECTORY_SEPARATOR.'ConnectionConfig'.\DIRECTORY_SEPARATOR.'SlaveConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ConnectionConfig'.\DIRECTORY_SEPARATOR.'ReplicaConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'ConnectionConfig'.\DIRECTORY_SEPARATOR.'ShardConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ConnectionConfig 
{
    private $url;
    private $dbname;
    private $host;
    private $port;
    private $user;
    private $password;
    private $overrideUrl;
    private $dbnameSuffix;
    private $applicationName;
    private $charset;
    private $path;
    private $memory;
    private $unixSocket;
    private $persistent;
    private $protocol;
    private $service;
    private $servicename;
    private $sessionMode;
    private $server;
    private $defaultDbname;
    private $sslmode;
    private $sslrootcert;
    private $sslcert;
    private $sslkey;
    private $sslcrl;
    private $pooled;
    private $multipleActiveResultSets;
    private $useSavepoints;
    private $instancename;
    private $connectstring;
    private $driver;
    private $platformService;
    private $autoCommit;
    private $schemaFilter;
    private $logging;
    private $profiling;
    private $profilingCollectBacktrace;
    private $profilingCollectSchemaErrors;
    private $serverVersion;
    private $driverClass;
    private $wrapperClass;
    private $shardManagerClass;
    private $shardChoser;
    private $shardChoserService;
    private $keepSlave;
    private $keepReplica;
    private $options;
    private $mappingTypes;
    private $defaultTableOptions;
    private $slaves;
    private $replicas;
    private $shards;
    private $_usedProperties = [];

    /**
     * A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function url($value): static
    {
        $this->_usedProperties['url'] = true;
        $this->url = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dbname($value): static
    {
        $this->_usedProperties['dbname'] = true;
        $this->dbname = $value;

        return $this;
    }

    /**
     * Defaults to "localhost" at runtime.
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
     * Defaults to null at runtime.
     * @default null
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
     * Defaults to "root" at runtime.
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
     * Defaults to null at runtime.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function password($value): static
    {
        $this->_usedProperties['password'] = true;
        $this->password = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @deprecated The "doctrine.dbal.override_url" configuration key is deprecated.
     * @return $this
     */
    public function overrideUrl($value): static
    {
        $this->_usedProperties['overrideUrl'] = true;
        $this->overrideUrl = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dbnameSuffix($value): static
    {
        $this->_usedProperties['dbnameSuffix'] = true;
        $this->dbnameSuffix = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function applicationName($value): static
    {
        $this->_usedProperties['applicationName'] = true;
        $this->applicationName = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function charset($value): static
    {
        $this->_usedProperties['charset'] = true;
        $this->charset = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function path($value): static
    {
        $this->_usedProperties['path'] = true;
        $this->path = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function memory($value): static
    {
        $this->_usedProperties['memory'] = true;
        $this->memory = $value;

        return $this;
    }

    /**
     * The unix socket to use for MySQL
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function unixSocket($value): static
    {
        $this->_usedProperties['unixSocket'] = true;
        $this->unixSocket = $value;

        return $this;
    }

    /**
     * True to use as persistent connection for the ibm_db2 driver
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function persistent($value): static
    {
        $this->_usedProperties['persistent'] = true;
        $this->persistent = $value;

        return $this;
    }

    /**
     * The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function protocol($value): static
    {
        $this->_usedProperties['protocol'] = true;
        $this->protocol = $value;

        return $this;
    }

    /**
     * True to use SERVICE_NAME as connection parameter instead of SID for Oracle
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function service($value): static
    {
        $this->_usedProperties['service'] = true;
        $this->service = $value;

        return $this;
    }

    /**
     * Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function servicename($value): static
    {
        $this->_usedProperties['servicename'] = true;
        $this->servicename = $value;

        return $this;
    }

    /**
     * The session mode to use for the oci8 driver
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sessionMode($value): static
    {
        $this->_usedProperties['sessionMode'] = true;
        $this->sessionMode = $value;

        return $this;
    }

    /**
     * The name of a running database server to connect to for SQL Anywhere.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function server($value): static
    {
        $this->_usedProperties['server'] = true;
        $this->server = $value;

        return $this;
    }

    /**
     * Override the default database (postgres) to connect to for PostgreSQL connexion.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultDbname($value): static
    {
        $this->_usedProperties['defaultDbname'] = true;
        $this->defaultDbname = $value;

        return $this;
    }

    /**
     * Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sslmode($value): static
    {
        $this->_usedProperties['sslmode'] = true;
        $this->sslmode = $value;

        return $this;
    }

    /**
     * The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sslrootcert($value): static
    {
        $this->_usedProperties['sslrootcert'] = true;
        $this->sslrootcert = $value;

        return $this;
    }

    /**
     * The path to the SSL client certificate file for PostgreSQL.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sslcert($value): static
    {
        $this->_usedProperties['sslcert'] = true;
        $this->sslcert = $value;

        return $this;
    }

    /**
     * The path to the SSL client key file for PostgreSQL.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sslkey($value): static
    {
        $this->_usedProperties['sslkey'] = true;
        $this->sslkey = $value;

        return $this;
    }

    /**
     * The file name of the SSL certificate revocation list for PostgreSQL.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function sslcrl($value): static
    {
        $this->_usedProperties['sslcrl'] = true;
        $this->sslcrl = $value;

        return $this;
    }

    /**
     * True to use a pooled server with the oci8/pdo_oracle driver
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function pooled($value): static
    {
        $this->_usedProperties['pooled'] = true;
        $this->pooled = $value;

        return $this;
    }

    /**
     * Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function multipleActiveResultSets($value): static
    {
        $this->_usedProperties['multipleActiveResultSets'] = true;
        $this->multipleActiveResultSets = $value;

        return $this;
    }

    /**
     * Use savepoints for nested transactions
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useSavepoints($value): static
    {
        $this->_usedProperties['useSavepoints'] = true;
        $this->useSavepoints = $value;

        return $this;
    }

    /**
     * Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function instancename($value): static
    {
        $this->_usedProperties['instancename'] = true;
        $this->instancename = $value;

        return $this;
    }

    /**
     * Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function connectstring($value): static
    {
        $this->_usedProperties['connectstring'] = true;
        $this->connectstring = $value;

        return $this;
    }

    /**
     * @default 'pdo_mysql'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function driver($value): static
    {
        $this->_usedProperties['driver'] = true;
        $this->driver = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function platformService($value): static
    {
        $this->_usedProperties['platformService'] = true;
        $this->platformService = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function autoCommit($value): static
    {
        $this->_usedProperties['autoCommit'] = true;
        $this->autoCommit = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function schemaFilter($value): static
    {
        $this->_usedProperties['schemaFilter'] = true;
        $this->schemaFilter = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function logging($value): static
    {
        $this->_usedProperties['logging'] = true;
        $this->logging = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function profiling($value): static
    {
        $this->_usedProperties['profiling'] = true;
        $this->profiling = $value;

        return $this;
    }

    /**
     * Enables collecting backtraces when profiling is enabled
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function profilingCollectBacktrace($value): static
    {
        $this->_usedProperties['profilingCollectBacktrace'] = true;
        $this->profilingCollectBacktrace = $value;

        return $this;
    }

    /**
     * Enables collecting schema errors when profiling is enabled
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function profilingCollectSchemaErrors($value): static
    {
        $this->_usedProperties['profilingCollectSchemaErrors'] = true;
        $this->profilingCollectSchemaErrors = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function serverVersion($value): static
    {
        $this->_usedProperties['serverVersion'] = true;
        $this->serverVersion = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function driverClass($value): static
    {
        $this->_usedProperties['driverClass'] = true;
        $this->driverClass = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function wrapperClass($value): static
    {
        $this->_usedProperties['wrapperClass'] = true;
        $this->wrapperClass = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @deprecated The "shard_manager_class" configuration is deprecated and not supported anymore using DBAL 3.
     * @return $this
     */
    public function shardManagerClass($value): static
    {
        $this->_usedProperties['shardManagerClass'] = true;
        $this->shardManagerClass = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @deprecated The "shard_choser" configuration is deprecated and not supported anymore using DBAL 3.
     * @return $this
     */
    public function shardChoser($value): static
    {
        $this->_usedProperties['shardChoser'] = true;
        $this->shardChoser = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @deprecated The "shard_choser_service" configuration is deprecated and not supported anymore using DBAL 3.
     * @return $this
     */
    public function shardChoserService($value): static
    {
        $this->_usedProperties['shardChoserService'] = true;
        $this->shardChoserService = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @deprecated The "keep_slave" configuration key is deprecated since doctrine-bundle 2.2. Use the "keep_replica" configuration key instead.
     * @return $this
     */
    public function keepSlave($value): static
    {
        $this->_usedProperties['keepSlave'] = true;
        $this->keepSlave = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function keepReplica($value): static
    {
        $this->_usedProperties['keepReplica'] = true;
        $this->keepReplica = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function option(string $key, mixed $value): static
    {
        $this->_usedProperties['options'] = true;
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function mappingType(string $name, mixed $value): static
    {
        $this->_usedProperties['mappingTypes'] = true;
        $this->mappingTypes[$name] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function defaultTableOption(string $name, mixed $value): static
    {
        $this->_usedProperties['defaultTableOptions'] = true;
        $this->defaultTableOptions[$name] = $value;

        return $this;
    }

    /**
     * @deprecated The "slaves" configuration key will be renamed to "replicas" in doctrine-bundle 3.0. "slaves" is deprecated since doctrine-bundle 2.2.
     * @return \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig|$this
     */
    public function slave(string $name, mixed $value = []): \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['slaves'] = true;
            $this->slaves[$name] = $value;

            return $this;
        }

        if (!isset($this->slaves[$name]) || !$this->slaves[$name] instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig) {
            $this->_usedProperties['slaves'] = true;
            $this->slaves[$name] = new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "slave()" has already been initialized. You cannot pass values the second time you call slave().');
        }

        return $this->slaves[$name];
    }

    /**
     * @return \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig|$this
     */
    public function replica(string $name, mixed $value = []): \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['replicas'] = true;
            $this->replicas[$name] = $value;

            return $this;
        }

        if (!isset($this->replicas[$name]) || !$this->replicas[$name] instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig) {
            $this->_usedProperties['replicas'] = true;
            $this->replicas[$name] = new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "replica()" has already been initialized. You cannot pass values the second time you call replica().');
        }

        return $this->replicas[$name];
    }

    /**
     * @deprecated The "shards" configuration is deprecated and not supported anymore using DBAL 3.
     * @return \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ShardConfig|$this
     */
    public function shard(mixed $value = []): \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ShardConfig|static
    {
        $this->_usedProperties['shards'] = true;
        if (!\is_array($value)) {
            $this->shards[] = $value;

            return $this;
        }

        return $this->shards[] = new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ShardConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('url', $value)) {
            $this->_usedProperties['url'] = true;
            $this->url = $value['url'];
            unset($value['url']);
        }

        if (array_key_exists('dbname', $value)) {
            $this->_usedProperties['dbname'] = true;
            $this->dbname = $value['dbname'];
            unset($value['dbname']);
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

        if (array_key_exists('password', $value)) {
            $this->_usedProperties['password'] = true;
            $this->password = $value['password'];
            unset($value['password']);
        }

        if (array_key_exists('override_url', $value)) {
            $this->_usedProperties['overrideUrl'] = true;
            $this->overrideUrl = $value['override_url'];
            unset($value['override_url']);
        }

        if (array_key_exists('dbname_suffix', $value)) {
            $this->_usedProperties['dbnameSuffix'] = true;
            $this->dbnameSuffix = $value['dbname_suffix'];
            unset($value['dbname_suffix']);
        }

        if (array_key_exists('application_name', $value)) {
            $this->_usedProperties['applicationName'] = true;
            $this->applicationName = $value['application_name'];
            unset($value['application_name']);
        }

        if (array_key_exists('charset', $value)) {
            $this->_usedProperties['charset'] = true;
            $this->charset = $value['charset'];
            unset($value['charset']);
        }

        if (array_key_exists('path', $value)) {
            $this->_usedProperties['path'] = true;
            $this->path = $value['path'];
            unset($value['path']);
        }

        if (array_key_exists('memory', $value)) {
            $this->_usedProperties['memory'] = true;
            $this->memory = $value['memory'];
            unset($value['memory']);
        }

        if (array_key_exists('unix_socket', $value)) {
            $this->_usedProperties['unixSocket'] = true;
            $this->unixSocket = $value['unix_socket'];
            unset($value['unix_socket']);
        }

        if (array_key_exists('persistent', $value)) {
            $this->_usedProperties['persistent'] = true;
            $this->persistent = $value['persistent'];
            unset($value['persistent']);
        }

        if (array_key_exists('protocol', $value)) {
            $this->_usedProperties['protocol'] = true;
            $this->protocol = $value['protocol'];
            unset($value['protocol']);
        }

        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('servicename', $value)) {
            $this->_usedProperties['servicename'] = true;
            $this->servicename = $value['servicename'];
            unset($value['servicename']);
        }

        if (array_key_exists('sessionMode', $value)) {
            $this->_usedProperties['sessionMode'] = true;
            $this->sessionMode = $value['sessionMode'];
            unset($value['sessionMode']);
        }

        if (array_key_exists('server', $value)) {
            $this->_usedProperties['server'] = true;
            $this->server = $value['server'];
            unset($value['server']);
        }

        if (array_key_exists('default_dbname', $value)) {
            $this->_usedProperties['defaultDbname'] = true;
            $this->defaultDbname = $value['default_dbname'];
            unset($value['default_dbname']);
        }

        if (array_key_exists('sslmode', $value)) {
            $this->_usedProperties['sslmode'] = true;
            $this->sslmode = $value['sslmode'];
            unset($value['sslmode']);
        }

        if (array_key_exists('sslrootcert', $value)) {
            $this->_usedProperties['sslrootcert'] = true;
            $this->sslrootcert = $value['sslrootcert'];
            unset($value['sslrootcert']);
        }

        if (array_key_exists('sslcert', $value)) {
            $this->_usedProperties['sslcert'] = true;
            $this->sslcert = $value['sslcert'];
            unset($value['sslcert']);
        }

        if (array_key_exists('sslkey', $value)) {
            $this->_usedProperties['sslkey'] = true;
            $this->sslkey = $value['sslkey'];
            unset($value['sslkey']);
        }

        if (array_key_exists('sslcrl', $value)) {
            $this->_usedProperties['sslcrl'] = true;
            $this->sslcrl = $value['sslcrl'];
            unset($value['sslcrl']);
        }

        if (array_key_exists('pooled', $value)) {
            $this->_usedProperties['pooled'] = true;
            $this->pooled = $value['pooled'];
            unset($value['pooled']);
        }

        if (array_key_exists('MultipleActiveResultSets', $value)) {
            $this->_usedProperties['multipleActiveResultSets'] = true;
            $this->multipleActiveResultSets = $value['MultipleActiveResultSets'];
            unset($value['MultipleActiveResultSets']);
        }

        if (array_key_exists('use_savepoints', $value)) {
            $this->_usedProperties['useSavepoints'] = true;
            $this->useSavepoints = $value['use_savepoints'];
            unset($value['use_savepoints']);
        }

        if (array_key_exists('instancename', $value)) {
            $this->_usedProperties['instancename'] = true;
            $this->instancename = $value['instancename'];
            unset($value['instancename']);
        }

        if (array_key_exists('connectstring', $value)) {
            $this->_usedProperties['connectstring'] = true;
            $this->connectstring = $value['connectstring'];
            unset($value['connectstring']);
        }

        if (array_key_exists('driver', $value)) {
            $this->_usedProperties['driver'] = true;
            $this->driver = $value['driver'];
            unset($value['driver']);
        }

        if (array_key_exists('platform_service', $value)) {
            $this->_usedProperties['platformService'] = true;
            $this->platformService = $value['platform_service'];
            unset($value['platform_service']);
        }

        if (array_key_exists('auto_commit', $value)) {
            $this->_usedProperties['autoCommit'] = true;
            $this->autoCommit = $value['auto_commit'];
            unset($value['auto_commit']);
        }

        if (array_key_exists('schema_filter', $value)) {
            $this->_usedProperties['schemaFilter'] = true;
            $this->schemaFilter = $value['schema_filter'];
            unset($value['schema_filter']);
        }

        if (array_key_exists('logging', $value)) {
            $this->_usedProperties['logging'] = true;
            $this->logging = $value['logging'];
            unset($value['logging']);
        }

        if (array_key_exists('profiling', $value)) {
            $this->_usedProperties['profiling'] = true;
            $this->profiling = $value['profiling'];
            unset($value['profiling']);
        }

        if (array_key_exists('profiling_collect_backtrace', $value)) {
            $this->_usedProperties['profilingCollectBacktrace'] = true;
            $this->profilingCollectBacktrace = $value['profiling_collect_backtrace'];
            unset($value['profiling_collect_backtrace']);
        }

        if (array_key_exists('profiling_collect_schema_errors', $value)) {
            $this->_usedProperties['profilingCollectSchemaErrors'] = true;
            $this->profilingCollectSchemaErrors = $value['profiling_collect_schema_errors'];
            unset($value['profiling_collect_schema_errors']);
        }

        if (array_key_exists('server_version', $value)) {
            $this->_usedProperties['serverVersion'] = true;
            $this->serverVersion = $value['server_version'];
            unset($value['server_version']);
        }

        if (array_key_exists('driver_class', $value)) {
            $this->_usedProperties['driverClass'] = true;
            $this->driverClass = $value['driver_class'];
            unset($value['driver_class']);
        }

        if (array_key_exists('wrapper_class', $value)) {
            $this->_usedProperties['wrapperClass'] = true;
            $this->wrapperClass = $value['wrapper_class'];
            unset($value['wrapper_class']);
        }

        if (array_key_exists('shard_manager_class', $value)) {
            $this->_usedProperties['shardManagerClass'] = true;
            $this->shardManagerClass = $value['shard_manager_class'];
            unset($value['shard_manager_class']);
        }

        if (array_key_exists('shard_choser', $value)) {
            $this->_usedProperties['shardChoser'] = true;
            $this->shardChoser = $value['shard_choser'];
            unset($value['shard_choser']);
        }

        if (array_key_exists('shard_choser_service', $value)) {
            $this->_usedProperties['shardChoserService'] = true;
            $this->shardChoserService = $value['shard_choser_service'];
            unset($value['shard_choser_service']);
        }

        if (array_key_exists('keep_slave', $value)) {
            $this->_usedProperties['keepSlave'] = true;
            $this->keepSlave = $value['keep_slave'];
            unset($value['keep_slave']);
        }

        if (array_key_exists('keep_replica', $value)) {
            $this->_usedProperties['keepReplica'] = true;
            $this->keepReplica = $value['keep_replica'];
            unset($value['keep_replica']);
        }

        if (array_key_exists('options', $value)) {
            $this->_usedProperties['options'] = true;
            $this->options = $value['options'];
            unset($value['options']);
        }

        if (array_key_exists('mapping_types', $value)) {
            $this->_usedProperties['mappingTypes'] = true;
            $this->mappingTypes = $value['mapping_types'];
            unset($value['mapping_types']);
        }

        if (array_key_exists('default_table_options', $value)) {
            $this->_usedProperties['defaultTableOptions'] = true;
            $this->defaultTableOptions = $value['default_table_options'];
            unset($value['default_table_options']);
        }

        if (array_key_exists('slaves', $value)) {
            $this->_usedProperties['slaves'] = true;
            $this->slaves = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig($v) : $v; }, $value['slaves']);
            unset($value['slaves']);
        }

        if (array_key_exists('replicas', $value)) {
            $this->_usedProperties['replicas'] = true;
            $this->replicas = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig($v) : $v; }, $value['replicas']);
            unset($value['replicas']);
        }

        if (array_key_exists('shards', $value)) {
            $this->_usedProperties['shards'] = true;
            $this->shards = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ShardConfig($v) : $v; }, $value['shards']);
            unset($value['shards']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['url'])) {
            $output['url'] = $this->url;
        }
        if (isset($this->_usedProperties['dbname'])) {
            $output['dbname'] = $this->dbname;
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
        if (isset($this->_usedProperties['password'])) {
            $output['password'] = $this->password;
        }
        if (isset($this->_usedProperties['overrideUrl'])) {
            $output['override_url'] = $this->overrideUrl;
        }
        if (isset($this->_usedProperties['dbnameSuffix'])) {
            $output['dbname_suffix'] = $this->dbnameSuffix;
        }
        if (isset($this->_usedProperties['applicationName'])) {
            $output['application_name'] = $this->applicationName;
        }
        if (isset($this->_usedProperties['charset'])) {
            $output['charset'] = $this->charset;
        }
        if (isset($this->_usedProperties['path'])) {
            $output['path'] = $this->path;
        }
        if (isset($this->_usedProperties['memory'])) {
            $output['memory'] = $this->memory;
        }
        if (isset($this->_usedProperties['unixSocket'])) {
            $output['unix_socket'] = $this->unixSocket;
        }
        if (isset($this->_usedProperties['persistent'])) {
            $output['persistent'] = $this->persistent;
        }
        if (isset($this->_usedProperties['protocol'])) {
            $output['protocol'] = $this->protocol;
        }
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['servicename'])) {
            $output['servicename'] = $this->servicename;
        }
        if (isset($this->_usedProperties['sessionMode'])) {
            $output['sessionMode'] = $this->sessionMode;
        }
        if (isset($this->_usedProperties['server'])) {
            $output['server'] = $this->server;
        }
        if (isset($this->_usedProperties['defaultDbname'])) {
            $output['default_dbname'] = $this->defaultDbname;
        }
        if (isset($this->_usedProperties['sslmode'])) {
            $output['sslmode'] = $this->sslmode;
        }
        if (isset($this->_usedProperties['sslrootcert'])) {
            $output['sslrootcert'] = $this->sslrootcert;
        }
        if (isset($this->_usedProperties['sslcert'])) {
            $output['sslcert'] = $this->sslcert;
        }
        if (isset($this->_usedProperties['sslkey'])) {
            $output['sslkey'] = $this->sslkey;
        }
        if (isset($this->_usedProperties['sslcrl'])) {
            $output['sslcrl'] = $this->sslcrl;
        }
        if (isset($this->_usedProperties['pooled'])) {
            $output['pooled'] = $this->pooled;
        }
        if (isset($this->_usedProperties['multipleActiveResultSets'])) {
            $output['MultipleActiveResultSets'] = $this->multipleActiveResultSets;
        }
        if (isset($this->_usedProperties['useSavepoints'])) {
            $output['use_savepoints'] = $this->useSavepoints;
        }
        if (isset($this->_usedProperties['instancename'])) {
            $output['instancename'] = $this->instancename;
        }
        if (isset($this->_usedProperties['connectstring'])) {
            $output['connectstring'] = $this->connectstring;
        }
        if (isset($this->_usedProperties['driver'])) {
            $output['driver'] = $this->driver;
        }
        if (isset($this->_usedProperties['platformService'])) {
            $output['platform_service'] = $this->platformService;
        }
        if (isset($this->_usedProperties['autoCommit'])) {
            $output['auto_commit'] = $this->autoCommit;
        }
        if (isset($this->_usedProperties['schemaFilter'])) {
            $output['schema_filter'] = $this->schemaFilter;
        }
        if (isset($this->_usedProperties['logging'])) {
            $output['logging'] = $this->logging;
        }
        if (isset($this->_usedProperties['profiling'])) {
            $output['profiling'] = $this->profiling;
        }
        if (isset($this->_usedProperties['profilingCollectBacktrace'])) {
            $output['profiling_collect_backtrace'] = $this->profilingCollectBacktrace;
        }
        if (isset($this->_usedProperties['profilingCollectSchemaErrors'])) {
            $output['profiling_collect_schema_errors'] = $this->profilingCollectSchemaErrors;
        }
        if (isset($this->_usedProperties['serverVersion'])) {
            $output['server_version'] = $this->serverVersion;
        }
        if (isset($this->_usedProperties['driverClass'])) {
            $output['driver_class'] = $this->driverClass;
        }
        if (isset($this->_usedProperties['wrapperClass'])) {
            $output['wrapper_class'] = $this->wrapperClass;
        }
        if (isset($this->_usedProperties['shardManagerClass'])) {
            $output['shard_manager_class'] = $this->shardManagerClass;
        }
        if (isset($this->_usedProperties['shardChoser'])) {
            $output['shard_choser'] = $this->shardChoser;
        }
        if (isset($this->_usedProperties['shardChoserService'])) {
            $output['shard_choser_service'] = $this->shardChoserService;
        }
        if (isset($this->_usedProperties['keepSlave'])) {
            $output['keep_slave'] = $this->keepSlave;
        }
        if (isset($this->_usedProperties['keepReplica'])) {
            $output['keep_replica'] = $this->keepReplica;
        }
        if (isset($this->_usedProperties['options'])) {
            $output['options'] = $this->options;
        }
        if (isset($this->_usedProperties['mappingTypes'])) {
            $output['mapping_types'] = $this->mappingTypes;
        }
        if (isset($this->_usedProperties['defaultTableOptions'])) {
            $output['default_table_options'] = $this->defaultTableOptions;
        }
        if (isset($this->_usedProperties['slaves'])) {
            $output['slaves'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig\SlaveConfig ? $v->toArray() : $v; }, $this->slaves);
        }
        if (isset($this->_usedProperties['replicas'])) {
            $output['replicas'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ReplicaConfig ? $v->toArray() : $v; }, $this->replicas);
        }
        if (isset($this->_usedProperties['shards'])) {
            $output['shards'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Doctrine\Dbal\ConnectionConfig\ShardConfig ? $v->toArray() : $v; }, $this->shards);
        }

        return $output;
    }

}
