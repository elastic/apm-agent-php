<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Events;

/**
 * Base Event class for all APM Intake Entities
 *
 * @version Intake API v2
 * @see Elastic\Tests\Events\BaseEventTest
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class BaseEvent
{

    /**
     * Current Timestamp with micro seconds
     *
     * @var int
     */
    private $timestamp;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->timestamp = (int)round(microtime(true) * 1000000);
    }

    /**
     * Get the Event's Timestamp
     *
     * @return int
     */
    final public function getTimestamp() : int
    {
        return $this->timestamp;
    }
}
