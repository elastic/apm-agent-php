<?php

namespace Symfony\Config\Doctrine\Dbal\ConnectionConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ShardConfig 
{
    private $id;
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
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function id($value): static
    {
        $this->_usedProperties['id'] = true;
        $this->id = $value;

        return $this;
    }

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

    public function __construct(array $value = [])
    {
        if (array_key_exists('id', $value)) {
            $this->_usedProperties['id'] = true;
            $this->id = $value['id'];
            unset($value['id']);
        }

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

        return $output;
    }

}
