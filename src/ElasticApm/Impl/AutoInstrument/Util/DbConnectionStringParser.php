<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbConnectionStringParser implements LoggableInterface
{
    use LoggableTrait;

    /** @var Logger */
    private $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function parse(string $dbConnectionString, ?string &$dbType, ?string &$dbName): void
    {
        $localLogger = $this->logger->inherit()->addContext(
            'dbConnectionString',
            $this->logger->possiblySecuritySensitive($dbConnectionString)
        );

        $dbType = null;
        $dbName = null;
        $dbTypePrefix = '';
        $posAfterDbTypePrefix = 0;
        $isDbTypePrefixFound = self::extractDbTypePrefix(
            $dbConnectionString,
            /* ref */ $dbTypePrefix,
            /* ref */ $posAfterDbTypePrefix,
            $localLogger
        );
        if (!$isDbTypePrefixFound) {
            ($loggerProxy = $localLogger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('DB type prefix not found in connection string');
            return;
        }

        $localLogger = $localLogger->addContext(
            'dbTypePrefix',
            $this->logger->possiblySecuritySensitive($dbTypePrefix)
        );
        $dbNameKey = 'dbname';
        /** @var ?string $dsnKey */
        $dsnKey = null;
        switch ($dbTypePrefix) {
            case 'cubrid':
                $dbType = Constants::SPAN_SUBTYPE_CUBRID;
                break;
            case 'dblib':
            case 'mssql':
                $dbType = Constants::SPAN_SUBTYPE_MSSQL;
                break;
            case 'firebird':
                $dbType = Constants::SPAN_SUBTYPE_FIREBIRD;
                break;
            case 'ibm':
                $dbType = Constants::SPAN_SUBTYPE_IBM_DB2;
                $dbNameKey = 'database';
                $dsnKey = 'DSN';
                break;
            case 'informix':
                $dbType = Constants::SPAN_SUBTYPE_INFORMIX;
                $dbNameKey = 'database';
                $dsnKey = 'DSN';
                break;
            case 'mysql':
                $dbType = Constants::SPAN_SUBTYPE_MYSQL;
                break;
            case 'oci':
                $dbType = Constants::SPAN_SUBTYPE_ORACLE;
                break;
            case 'odbc':
                $dbType = Constants::SPAN_SUBTYPE_ODBC;
                self::extractDbNameODBC($dbConnectionString, $posAfterDbTypePrefix, /* ref */ $dbName);
                return;
            case 'pgsql':
                $dbType = Constants::SPAN_SUBTYPE_POSTGRESQL;
                break;
            case 'sqlite':
                $dbType = Constants::SPAN_SUBTYPE_SQLITE;
                self::extractDbNameSQLite($dbConnectionString, $posAfterDbTypePrefix, /* ref */ $dbName);
                return;
            case 'sqlsrv':
                $dbType = Constants::SPAN_SUBTYPE_MSSQL;
                $dbNameKey = 'database';
                break;
            default:
                ($loggerProxy = $localLogger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Unknown DB type in connection string prefix',
                    ['dbTypePrefix' => $dbTypePrefix]
                );
                return;
        }
        $localLogger->addContext('dbType', $dbType);
        self::extractDbName(
            $dbConnectionString,
            $posAfterDbTypePrefix,
            $dbNameKey,
            $dsnKey,
            $dbName /* <- ref */,
            $localLogger
        );
    }

    private static function extractDbTypePrefix(
        string $dbConnectionString,
        /* ref */ string &$dbTypePrefix,
        /* ref */ int &$posAfterDbTypePrefix,
        Logger $localLogger
    ): bool {
        $colonPos = strpos($dbConnectionString, ':');
        if ($colonPos === false) {
            ($loggerProxy = $localLogger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Colon (\':\') not found in DB connection string');
            return false;
        }

        $dbTypePrefix = substr($dbConnectionString, 0, $colonPos);
        $posAfterDbTypePrefix = $colonPos + 1;
        return true;
    }

    /**
     * @param string $list
     * @param int    $startIndex
     * @param string $separator
     *
     * @return iterable<string>
     */
    private static function iterateList(string $list, int $startIndex, string $separator): iterable
    {
        $nextPos = $startIndex;
        $listLen = strlen($list);
        while ($nextPos < $listLen) {
            $currentPos = $nextPos;
            $sepPos = strpos($list, $separator, $currentPos);
            $nextPos = (($sepPos === false) ? $listLen : $sepPos) + 1;
            yield substr($list, $currentPos, $nextPos - $currentPos - 1);
        }
    }

    private static function splitKeyValuePair(
        string $keyValuePair,
        string &$key,
        string &$value,
        Logger $localLogger
    ): bool {
        $sepPos = strpos($keyValuePair, '=');
        if ($sepPos === false) {
            ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Key-value pair separator not found', ['keyValuePair' => $keyValuePair]);
            return false;
        }

        $key = trim(substr($keyValuePair, /* offset: */ 0, $sepPos));
        $value = trim(substr($keyValuePair, /* offset: */ $sepPos + 1));
        return true;
    }

    private static function extractDbName(
        string $dbConnectionString,
        int $startIndex,
        string $dbNameKey,
        ?string $dsnKey,
        ?string &$dbName,
        Logger $localLogger
    ): void {
        $localLogger->addContext('dbNameKey', $dbNameKey);
        foreach (self::iterateList($dbConnectionString, $startIndex, /* separator: */ ';') as $keyValuePair) {
            $key = '';
            $value = '';
            if (!self::splitKeyValuePair($keyValuePair, /* ref */ $key, /* ref */ $value, $localLogger)) {
                continue;
            }
            if (strcasecmp($key, $dbNameKey) == 0) {
                $dbName = $value;
                break;
            } elseif (($dsnKey !== null) && (strcasecmp($key, $dsnKey) == 0)) {
                $dbName = $dsnKey . '=' . $value;
                break;
            }
        }

        if ($dbName === null) {
            ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Key-value pair with DB name key not found'
            );
        }
    }

    private static function extractDbNameSQLite(string $dbConnectionString, int $startIndex, ?string &$dbName): void
    {
        if ($startIndex === strlen($dbConnectionString)) {
            $dbName = Constants::SQLITE_TEMP_DB;
            return;
        }

        if ($dbConnectionString === 'sqlite::memory:') {
            $dbName = 'memory';
            return;
        }

        $dbName = substr($dbConnectionString, $startIndex);
    }

    private static function extractDbNameODBC(string $dbConnectionString, int $startIndex, ?string &$dbName): void
    {
        $dbName = 'DSN=' . substr($dbConnectionString, $startIndex);
    }
}
