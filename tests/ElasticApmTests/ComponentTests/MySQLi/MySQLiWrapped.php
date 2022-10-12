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
use PHPUnit\Framework\TestCase;

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

    /** @var bool */
    private $isOOPApi;

    public function __construct(mysqli $wrappedObj, bool $isOOPApi)
    {
        $this->wrappedObj = $wrappedObj;
        $this->isOOPApi = $isOOPApi;
    }

    public function ping(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->ping()
            : mysqli_ping($this->wrappedObj);
    }

    public function selectDb(string $dbName): bool
    {
        return $this->isOOPApi
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
        $result = $this->isOOPApi ? $this->wrappedObj->query($query) : mysqli_query($this->wrappedObj, $query);
        return is_bool($result) ? $result : new MySQLiResultWrapped($result, $this->isOOPApi);
    }

    public function realQuery(string $query): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->real_query($query)
            : mysqli_real_query($this->wrappedObj, $query);
    }

    public function multiQuery(string $query): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->multi_query($query)
            : mysqli_multi_query($this->wrappedObj, $query);
    }

    public function moreResults(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->more_results() : mysqli_more_results($this->wrappedObj);
    }

    public function nextResult(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->next_result() : mysqli_next_result($this->wrappedObj);
    }

    /**
     * @return false|MySQLiResultWrapped
     */
    public function storeResult()
    {
        $result = $this->isOOPApi ? $this->wrappedObj->store_result() : mysqli_store_result($this->wrappedObj);
        return $result === false ? false : new MySQLiResultWrapped($result, $this->isOOPApi);
    }

    public function beginTransaction(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->begin_transaction() : mysqli_begin_transaction($this->wrappedObj);
    }

    public function commit(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->commit() : mysqli_commit($this->wrappedObj);
    }

    public function rollback(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->rollback() : mysqli_rollback($this->wrappedObj);
    }

    /**
     * @param string $query
     *
     * @return false|MySQLiStmtWrapped
     */
    public function prepare(string $query)
    {
        $result = $this->isOOPApi
            ? $this->wrappedObj->prepare($query)
            : mysqli_prepare($this->wrappedObj, $query);
        return $result === false ? false : new MySQLiStmtWrapped($result, $this->isOOPApi);
    }

    public function error(): string
    {
        $result = $this->isOOPApi ? $this->wrappedObj->error : mysqli_error($this->wrappedObj);
        TestCase::assertNotNull($result);
        return $result;
    }

    public function close(): bool
    {
        return $this->isOOPApi ? $this->wrappedObj->close() : mysqli_close($this->wrappedObj);
    }
}
