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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use PHPUnit\Framework\Assert;

final class TraceActual
{
    /** @var TransactionDto */
    public $rootTransaction;

    /** @var array<string, TransactionDto> */
    public $idToTransaction;

    /** @var array<string, SpanDto> */
    public $idToSpan;

    /**
     * @param array<string, TransactionDto> $idToTransaction
     * @param array<string, SpanDto>        $idToSpan
     */
    public function __construct(array $idToTransaction, array $idToSpan)
    {
        $this->rootTransaction = self::findRootTransaction($idToTransaction);
        $this->idToTransaction = $idToTransaction;
        $this->idToSpan = $idToSpan;
    }

    /**
     * @param array<string, TransactionDto> $idToTransaction
     *
     * @return TransactionDto
     */
    public static function findRootTransaction(array $idToTransaction): TransactionDto
    {
        /** @var ?TransactionDto $rootTransaction */
        $rootTransaction = null;
        foreach ($idToTransaction as $currentTransaction) {
            if ($currentTransaction->parentId === null) {
                Assert::assertNull($rootTransaction, 'Found more than one root transaction');
                $rootTransaction = $currentTransaction;
            }
        }
        Assert::assertNotNull(
            $rootTransaction,
            'Root transaction not found. ' . LoggableToString::convert(['idToTransaction' => $idToTransaction])
        );
        /** @var TransactionDto $rootTransaction */
        return $rootTransaction;
    }
}
