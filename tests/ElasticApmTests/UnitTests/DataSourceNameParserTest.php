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

use Elastic\Apm\Impl\AutoInstrument\DataSourceNameParser;
use Elastic\Apm\Impl\Constants;
use ElasticApmTests\Util\TestCaseBase;

class DataSourceNameParserTest extends TestCaseBase
{
    /**
     * @return iterable<array{string, string}>
     */
    public function dataProviderForTest(): iterable
    {
        //////////////////////////////
        //
        // SQLite
        //

        // https://www.php.net/manual/en/ref.pdo-sqlite.php
        yield ['sqlite:/tmp/foo.db', Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE];
        // https://stackoverflow.com/questions/2425531/create-sqlite-database-in-memory
        yield ['sqlite::memory:', Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE];

        //////////////////////////////
        //
        // MySQL
        //

        // https://www.php.net/manual/en/ref.pdo-mysql.php
        yield ['mysql:host=xxx;port=xxx;dbname=xxx', Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL];

        //////////////////////////////
        //
        // PostgreSQL
        //

        // https://www.postgresqltutorial.com/postgresql-php/connect/
        yield ['pgsql:host=1;port=2;dbname=3;user=4;password=5', Constants::SPAN_TYPE_DB_SUBTYPE_POSTGRESQL];

        //////////////////////////////
        //
        // Oracle
        //

        // https://www.php.net/manual/en/ref.pdo-oci.php
        yield ['oci:dbname=DB_FOO;host=10.10.48.245', Constants::SPAN_TYPE_DB_SUBTYPE_ORACLE];
        $oracleTns = '(DESCRIPTION =
                            (ADDRESS_LIST =
                              (ADDRESS = (PROTOCOL = TCP)(HOST = yourip)(PORT = 1521))
                            )
                            (CONNECT_DATA =
                              (SERVICE_NAME = orcl)
                            )
                      )';
        yield ['oci:dbname=' . $oracleTns, Constants::SPAN_TYPE_DB_SUBTYPE_ORACLE];

        //////////////////////////////
        //
        // Microsoft SQL Server
        //

        // https://docs.microsoft.com/en-us/sql/connect/php/pdo-query?view=sql-server-ver15
        yield ['sqlsrv:server=(local) ; Database = AdventureWorks', Constants::SPAN_TYPE_DB_SUBTYPE_MSSQL];
        yield ['sqlsrv:server=my_serverName ; Database = my_databaseName', Constants::SPAN_TYPE_DB_SUBTYPE_MSSQL];

        // https://www.php.net/manual/en/ref.pdo-dblib.php
        yield ['dblib:host=my_hostname:1234;dbname=my_dbname', Constants::SPAN_TYPE_DB_SUBTYPE_MSSQL];

        //////////////////////////////
        //
        //  IBM DB2
        //

        // https://www.php.net/manual/en/ref.pdo-ibm.connection.php
        yield ['ibm:DSN=DB2_9', Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2];
        yield [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=11.22.33.444;PORT=56789;PROTOCOL=TCPIP;',
            Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2,
        ];
        yield [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=11.22.33.444;PORT=56789;PROTOCOL=TCPIP;'
            . 'UID=testuser;PWD=testpass',
            Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2,
        ];

        // https://www.ibm.com/docs/en/db2/11.5?topic=pdo-connecting-data-server-database
        yield ['ibm:SAMPLE', Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2];

        //////////////////////////////
        //
        // ODBC
        //

        // https://www.php.net/manual/en/ref.pdo-odbc.php
        yield ['odbc:SOURCENAME', Constants::SPAN_TYPE_DB_SUBTYPE_ODBC];

        //////////////////////////////
        //
        // CUBRID
        //

        // https://www.php.net/manual/en/ref.pdo-cubrid.php
        yield ['cubrid:dbname=demodb;host=localhost;port=33000', Constants::SPAN_TYPE_DB_SUBTYPE_CUBRID];

        //////////////////////////////
        //
        // Firebird
        //

        // https://www.php.net/manual/en/ref.pdo-firebird.php
        yield ['firebird:dbname=T:\\Klimreg.GDB', Constants::SPAN_TYPE_DB_SUBTYPE_FIREBIRD];

        //////////////////////////////
        //
        // Informix
        //

        // https://www.ibm.com/docs/en/informix-servers/14.10?topic=SSGU8G_14.1.0/com.ibm.virtapp.doc/TD_item2.htm
        yield [
            'informix:host=informixva; service=9088;database=stores; server=demo_on; protocol=onsoctcp;'
            . 'EnableScrollableCursors=1;', Constants::SPAN_TYPE_DB_SUBTYPE_INFORMIX,
        ];

        //////////////////////////////
        //
        // Unknown
        //

        yield ['dummy' . 'EnableScrollableCursors=1;', Constants::SPAN_TYPE_DB_SUBTYPE_UNKNOWN];
    }

    /**
     * @dataProvider dataProviderForTest
     *
     * @param string $dsn
     * @param string $expectedSubtype
     */
    public function test(string $dsn, string $expectedSubtype): void
    {
        /** var string */
        $actualSubtype = '';
        DataSourceNameParser::parse($dsn, /* ref */ $actualSubtype);
        self::assertEquals($expectedSubtype, $actualSubtype);
    }
}
