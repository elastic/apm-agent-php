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

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\AutoInstrument\Util\DbConnectionStringParser;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LoggableToString;
use ElasticApmTests\Util\TestCaseBase;

class DbConnectionStringParserTest extends TestCaseBase
{
    private const EXPECTED_DB_TYPE_KEY = 'EXPECTED_DB_TYPE';
    private const EXPECTED_DB_NAME_KEY = 'EXPECTED_DB_NAME';

    /**
     * @return array<string, ?string>
     */
    private static function buildExpected(?string $dbType, ?string $dbName): array
    {
        return [
            self::EXPECTED_DB_TYPE_KEY => $dbType,
            self::EXPECTED_DB_NAME_KEY => $dbName
        ];
    }

    /**
     * @return iterable<array{string, array<string, ?string>}>
     */
    public function dataProviderForTest(): iterable
    {
        //////////////////////////////
        //
        // SQLite
        //
        $dbType = Constants::SPAN_SUBTYPE_SQLITE;

        // https://www.php.net/manual/en/ref.pdo-sqlite.connection.php
        // To access a database on disk, the absolute path has to be appended to the DSN prefix.
        yield [
            'sqlite:/opt/databases/mydb.sq3',
            self::buildExpected($dbType, /* dbName: */ '/opt/databases/mydb.sq3')
        ];
        // To create a database in memory, :memory: has to be appended to the DSN prefix
        yield [
            'sqlite::memory:',
            self::buildExpected($dbType, /* dbName: */ 'memory')
        ];
        // If the DSN consists of the DSN prefix only, a temporary database is used,
        // which is deleted when the connection is closed
        yield [
            'sqlite:',
            self::buildExpected($dbType, /* dbName: */ Constants::SQLITE_TEMP_DB)
        ];

        yield [
            'sqlite',
            self::buildExpected(/* dbType: */ null, /* dbName: */ null)
        ];

        // //////////////////////////////
        // //
        // // MySQL
        // //
        $dbType = Constants::SPAN_SUBTYPE_MYSQL;

        // https://www.php.net/manual/en/ref.pdo-mysql.connection.php
        yield [
            'mysql:host=localhost;dbname=my_db_1',
            self::buildExpected($dbType, /* dbName: */ 'my_db_1')
        ];
        yield [
            'mysql:host=localhost;port=3307;dbname=my_db_2',
            self::buildExpected($dbType, /* dbName: */ 'my_db_2')
        ];
        yield [
            'mysql:host=localhost;port=3307;dbname=my_db_3;something_else',
            self::buildExpected($dbType, /* dbName: */ 'my_db_3')
        ];
        yield [
            'mysql:unix_socket=/tmp/mysql.sock;dbname=my_db_4',
            self::buildExpected($dbType, /* dbName: */ 'my_db_4')
        ];
        yield [
            'mysql:unix_socket=/tmp/mysql.sock;dbname=my_db_5;something_else',
            self::buildExpected($dbType, /* dbName: */ 'my_db_5')
        ];
        yield [
            'mysql:dbname=my_db_6',
            self::buildExpected($dbType, /* dbName: */ 'my_db_6')
        ];
        yield [
            'mysql:dbname=my_db_7;',
            self::buildExpected($dbType, /* dbName: */ 'my_db_7')
        ];
        yield [
            'mysql:dbname = my_db_8  ;something_else',
            self::buildExpected($dbType, /* dbName: */ 'my_db_8')
        ];
        yield [
            "mysql:something_else;dbname\t= my_db_9  \t",
            self::buildExpected($dbType, /* dbName: */ 'my_db_9')
        ];
        yield [
            'mysql:dbname=;something_else',
            self::buildExpected($dbType, /* dbName: */ '')
        ];
        yield [
            'mysql:something_else;dbname=',
            self::buildExpected($dbType, /* dbName: */ '')
        ];
        yield [
            'mysql:dbname;something_else',
            self::buildExpected($dbType, /* dbName: */ null)
        ];
        yield [
            'mysql:something_else;dbname',
            self::buildExpected($dbType, /* dbName: */ null)
        ];

        //////////////////////////////
        //
        // PostgreSQL
        //
        $dbType = Constants::SPAN_SUBTYPE_POSTGRESQL;

        // https://www.php.net/manual/en/ref.pdo-pgsql.connection.php
        yield [
            'pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass',
            self::buildExpected($dbType, /* dbName: */ 'testdb')
        ];

        // //////////////////////////////
        // //
        // // Oracle
        // //
        $dbType = Constants::SPAN_SUBTYPE_ORACLE;

        // https://www.php.net/manual/en/ref.pdo-oci.connection.php
        // Connect to a database defined in tnsnames.ora
        yield [
            'oci:dbname=mydb',
            self::buildExpected($dbType, /* dbName: */ 'mydb')
        ];
        // Connect using the Oracle Instant Client
        yield [
            'oci:dbname=//localhost:1521/mydb',
            self::buildExpected($dbType, /* dbName: */ '//localhost:1521/mydb')
        ];

        // https://www.php.net/manual/en/ref.pdo-oci.php
        yield [
            'oci:dbname=my_db_1;host=10.10.48.245',
            self::buildExpected($dbType, /* dbName: */ 'my_db_1')
        ];
        yield [
            'oci:;dbname=my_db_2;host=10.10.48.245',
            self::buildExpected($dbType, /* dbName: */ 'my_db_2')
        ];
        yield [
            'oci:;host=10.10.48.245;dbname=my_db_3',
            self::buildExpected($dbType, /* dbName: */ 'my_db_3')
        ];

        //////////////////////////////
        //
        // Microsoft SQL Server
        //
        $dbType = Constants::SPAN_SUBTYPE_MSSQL;

        // https://www.php.net/manual/en/ref.pdo-sqlsrv.connection.php
        yield [
            'sqlsrv:Server=localhost;Database=testdb',
            self::buildExpected($dbType, /* dbName: */ 'testdb')
        ];

        // https://docs.microsoft.com/en-us/sql/connect/php/pdo-query?view=sql-server-ver15
        yield [
            'sqlsrv:server=(local) ; Database = AdventureWorks',
            self::buildExpected($dbType, /* dbName: */ 'AdventureWorks')
        ];
        yield [
            'sqlsrv:server=my_serverName ; database = my_databaseName',
            self::buildExpected($dbType, /* dbName: */ 'my_databaseName')
        ];

        // https://www.php.net/manual/en/ref.pdo-dblib.connection.php
        yield [
            'mssql:host=localhost;dbname=testdb',
            self::buildExpected($dbType, /* dbName: */ 'testdb')
        ];

        // https://www.php.net/manual/en/ref.pdo-dblib.php
        yield [
            'dblib:host=my_hostname:1234;dbname=my_dbname',
            self::buildExpected($dbType, /* dbName: */ 'my_dbname')
        ];
        yield [
            'dblib:version=7.0;charset=UTF-8;host=domain.example.com;dbname=example;',
            self::buildExpected($dbType, /* dbName: */ 'example')
        ];

        //////////////////////////////
        //
        //  IBM DB2
        //
        $dbType = Constants::SPAN_SUBTYPE_IBM_DB2;

        // https://www.php.net/manual/en/ref.pdo-ibm.connection.php
        // ... connecting to an DB2 database cataloged as DB2_9 in db2cli.ini:
        // db2cli.ini:
        //      [DB2_9]
        //      Database=testdb
        yield [
            'ibm:DSN=DB2_9',
            self::buildExpected($dbType, /* dbName: */ 'DSN=DB2_9')
        ];
        yield [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=11.22.33.444;PORT=56789;PROTOCOL=TCPIP;',
            self::buildExpected($dbType, /* dbName: */ 'testdb')
        ];

        //////////////////////////////
        //
        // ODBC
        //
        $dbType = Constants::SPAN_SUBTYPE_ODBC;

        // https://www.php.net/manual/en/ref.pdo-odbc.connection.php
        // ... connecting to an ODBC database cataloged as testdb in the ODBC driver manager
        yield [
            'odbc:testdb',
            self::buildExpected($dbType, /* dbName: */ 'DSN=testdb')
        ];
        // connecting to an IBM DB2 database named SAMPLE using the full ODBC DSN
        // ... connecting to an IBM DB2 database named SAMPLE using the full ODBC DSN syntax
        // yield [
        //     'odbc:DRIVER={IBM DB2 ODBC DRIVER};HOSTNAME=localhost;PORT=50000;DATABASE=SAMPLE;PROTOCOL=TCPIP'
        //     . ';UID=db2inst1;PWD=ibmdb2;',
        //     self::buildExpected(Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2, /* dbName: */ 'SAMPLE'),
        // ];

        //////////////////////////////
        //
        // CUBRID
        //
        $dbType = Constants::SPAN_SUBTYPE_CUBRID;

        // https://www.php.net/manual/en/ref.pdo-cubrid.connection.php
        yield [
            'cubrid:host=localhost;port=33000;dbname=demodb',
            self::buildExpected($dbType, /* dbName: */ 'demodb')
        ];

        //////////////////////////////
        //
        // Firebird
        //
        $dbType = Constants::SPAN_SUBTYPE_FIREBIRD;

        // https://www.php.net/manual/en/ref.pdo-firebird.connection.php
        // ... connecting to Firebird databases
        yield [
            'firebird:dbname=/path/to/DATABASE.FDB',
            self::buildExpected($dbType, /* dbName: */ '/path/to/DATABASE.FDB')
        ];
        // ... connecting to a Firebird database using hostname port and path
        yield [
            'firebird:dbname=hostname/port:/path/to/DATABASE.FDB',
            self::buildExpected($dbType, /* dbName: */ 'hostname/port:/path/to/DATABASE.FDB')
        ];
        // ... connecting to a Firebird database employee.fdb using localhost
        yield [
            'firebird:dbname=localhost:/var/lib/firebird/2.5/data/employee.fdb',
            self::buildExpected($dbType, /* dbName: */ 'localhost:/var/lib/firebird/2.5/data/employee.fdb')
        ];
        // ... connecting to a Firebird database test.fdb which has been created using dialect 1
        yield [
            'firebird:dbname=localhost:/var/lib/firebird/2.5/data/test.fdb;charset=utf-8;dialect=1',
            self::buildExpected($dbType, /* dbName: */ 'localhost:/var/lib/firebird/2.5/data/test.fdb')
        ];

        // https://www.php.net/manual/en/ref.pdo-firebird.php
        yield [
            'firebird:dbname=T:\Klimreg.GDB',
            self::buildExpected($dbType, /* dbName: */ 'T:\Klimreg.GDB')
        ];

        //////////////////////////////
        //
        // Informix
        //
        $dbType = Constants::SPAN_SUBTYPE_INFORMIX;

        // https://www.php.net/manual/en/ref.pdo-informix.connection.php
        // ... connecting to an Informix database cataloged as Infdrv33 in odbc.ini
        yield [
            'informix:DSN=Infdrv33',
            self::buildExpected($dbType, /* dbName: */ 'DSN=Infdrv33')
        ];
        // ...  connecting to an Informix database named common_db using the Informix connection string syntax
        yield [
            "informix:host=host.domain.com; service=9800;
                database=common_db; server=ids_server; protocol=onsoctcp;
                EnableScrollableCursors=1",
            self::buildExpected($dbType, /* dbName: */ 'common_db'),
        ];

        // https://www.ibm.com/docs/en/informix-servers/14.10?topic=SSGU8G_14.1.0/com.ibm.virtapp.doc/TD_item2.htm
        yield [
            "informix:host=informixva; service=9088;
                database=stores; server=demo_on; protocol=onsoctcp;
                EnableScrollableCursors=1;",
            self::buildExpected($dbType, /* dbName: */ 'stores'),
        ];

        //////////////////////////////
        //
        // Unknown DB type
        //
        $dbType = null;

        yield [
            'dummy' . 'EnableScrollableCursors=1;',
            self::buildExpected($dbType, /* dbName: */ null)
        ];

        yield [
            'dummy:' . 'dbname=unused_name;',
            self::buildExpected($dbType, /* dbName: */ null)
        ];
    }

    /**
     * @dataProvider dataProviderForTest
     *
     * @param string $dsn
     * @param array<string, ?string> $expected
     */
    public function test(string $dsn, array $expected): void
    {
        /** @var ?string $actualDbType */
        $actualDbType = null;
        /** @var ?string $actualDbName */
        $actualDbName = null;
        $parser = new DbConnectionStringParser(self::noopLoggerFactory());
        $parser->parse($dsn, /* ref */ $actualDbType, /* ref */ $actualDbName);
        $dbgCtx = LoggableToString::convert(
            [
                '$dsn' => $dsn,
                '$expected' => $expected,
                '$actualDbType' => $actualDbType,
                '$actualDbName' => $actualDbName,
            ]
        );
        self::assertSame($expected[self::EXPECTED_DB_TYPE_KEY], $actualDbType, $dbgCtx);
        self::assertSame($expected[self::EXPECTED_DB_NAME_KEY], $actualDbName, $dbgCtx);
    }
}
