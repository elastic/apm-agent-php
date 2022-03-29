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

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DataSourceNameParser
{
    use StaticClassTrait;

    /** @var ?array<string, string> */
    private static $cachedDsnPrefixToSubtype = null;

    /**
     * @return array<string, string>
     */
    private static function getDsnPrefixToSubtype(): array
    {
        if (self::$cachedDsnPrefixToSubtype === null) {
            self::$cachedDsnPrefixToSubtype = [
                'sqlite:'   => Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE,
                'mysql:'    => Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL,
                'pgsql:'    => Constants::SPAN_TYPE_DB_SUBTYPE_POSTGRESQL,
                'oci:'      => Constants::SPAN_TYPE_DB_SUBTYPE_ORACLE,
                'sqlsrv:'   => Constants::SPAN_TYPE_DB_SUBTYPE_MSSQL,
                'dblib:'    => Constants::SPAN_TYPE_DB_SUBTYPE_MSSQL,
                'ibm:'      => Constants::SPAN_TYPE_DB_SUBTYPE_IBM_DB2,
                'odbc:'     => Constants::SPAN_TYPE_DB_SUBTYPE_ODBC,
                'cubrid:'   => Constants::SPAN_TYPE_DB_SUBTYPE_CUBRID,
                'firebird:' => Constants::SPAN_TYPE_DB_SUBTYPE_FIREBIRD,
                'informix:' => Constants::SPAN_TYPE_DB_SUBTYPE_INFORMIX,
            ];
        }

        return self::$cachedDsnPrefixToSubtype;
    }

    public static function parse(string $dsn, string &$dbSpanSubtype): void
    {
        foreach (self::getDsnPrefixToSubtype() as $dsnPrefix => $subtype) {
            if (TextUtil::isPrefixOf($dsnPrefix, $dsn, /* isCaseSensitive: */ false)) {
                $dbSpanSubtype = $subtype;
                return;
            }
        }

        $dbSpanSubtype = Constants::SPAN_TYPE_DB_SUBTYPE_UNKNOWN;
    }
}
