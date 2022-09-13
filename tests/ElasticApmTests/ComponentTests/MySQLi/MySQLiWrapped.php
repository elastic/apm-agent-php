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

namespace ElasticApmTests\ComponentTests\MySQLi;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use mysqli;
use mysqli_stmt;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySQLiWrapped implements LoggableInterface
{
    use LoggableTrait;

    /** @var mysqli */
    private $wrappedObj;

    /** @var ApiKind */
    private $apiKind;

    public function __construct(mysqli $wrappedObj, ApiKind $apiKind)
    {
        $this->wrappedObj = $wrappedObj;
        $this->apiKind = $apiKind;
    }

    public function setCharSet(string $charset): bool
    {
        return $this->apiKind->isOOP()
            ? $this->wrappedObj->set_charset($charset)
            : mysqli_set_charset($this->wrappedObj, $charset);
    }

    public function ping(): bool
    {
        return $this->apiKind->isOOP()
            ? $this->wrappedObj->ping()
            : mysqli_ping($this->wrappedObj);
    }

    public function selectDb(string $dbName): bool
    {
        return $this->apiKind->isOOP()
            ? $this->wrappedObj->select_db($dbName)
            : mysqli_select_db($this->wrappedObj, $dbName);
    }

    /**
     * @param string $query
     *
     * @return bool|MySQLiResultWrapped
     */
    public function query(string $query)
    {
        $result = $this->apiKind->isOOP()
            ? $this->wrappedObj->query($query)
            : mysqli_query($this->wrappedObj, $query);
        return is_bool($result) ? $result : new MySQLiResultWrapped($result, $this->apiKind);
    }

    /**
     * @param string $query
     *
     * @return false|MySQLiStmtWrapped
     */
    public function prepare(string $query)
    {
        $result = $this->apiKind->isOOP()
            ? $this->wrappedObj->prepare($query)
            : mysqli_prepare($this->wrappedObj, $query);
        return $result === false ? false : new MySQLiStmtWrapped($result, $this->apiKind);
    }
}
