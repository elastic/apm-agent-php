<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Events;

/**
 * Transaction Event
 *
 * @version Intake API v2
 * @see Elastic\Tests\Events\TransactionTest
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class Transaction extends Event
{

    /**
     * @return bool
     */
    final public function isSampled() : bool
    {
        // TODO
        return true;
    }

    /**
     * Get Type of Transaction
     *
     * @return string
     */
    final public function getType() : string
    {
        // TODO
        return 'request';
    }
}
