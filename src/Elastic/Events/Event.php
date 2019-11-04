<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Events;

/**
 * Base class for all APM Span, Transaction and Error Events
 *
 * @version Intake API v2
 * @see Elastic\Tests\Events\EventTest
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class Event extends BaseEvent
{

    /**
     * Event Id
     *
     * @var string
     */
    private $id;

    /**
     * Id of the whole trace forest and is used to uniquely identify a distributed trace through a system
     * @link https://www.w3.org/TR/trace-context/#trace-id
     *
     * @var string
     */
    private $traceId;

    /**
     * Id of parent span or parent transaction
     *
     * @link https://www.w3.org/TR/trace-context/#parent-id
     *
     * @var string
     */
    private $parentId = null;

    /**
     * Init the Trace with an Id
     *
     * @param int $idLength, Def: 64
     */
    public function __construct(int $idLength = 64)
    {
        parent::__construct();
        $this->id      = $this->generateId($idLength);
        $this->traceId = $this->generateId(128);
    }

    /**
     * Set the Parent Event
     */
    public function setParent(Event $parent) : void
    {
        $this->traceId  = $parent->getTraceId();
        $this->parentId = $parent->getId();
    }

    /**
     * Get the Event Id
     */
    final public function getId() : string
    {
        return $this->id;
    }

    /**
     * Get the Event's Trace Id
     */
    final public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * Get the Event's parent Id
     *
     * @return string|null
     */
    final public function getParentId() : ?string
    {
        return $this->parentId;
    }

    /**
     * Has this Event a Parent?
     *
     * @return bool
     */
    final public function hasParent() : bool
    {
        return ($this->getParentId() !== null);
    }

    /**
     * Generate random hex bits for Event IDs
     *
     * @param int $size
     * @return string
     *
     * @throws \Exception
     */
    final protected function generateId(int $size) : string
    {
        return bin2hex(random_bytes($size / 8));
    }

    /**
     * Get the Paylaod that all Events shared
     *
     * @return array
     */
    final protected function getBasePayload() : array
    {
        $payload = [
            'timestamp' => $this->getTimestamp(),
            'id'        => $this->getId(),
            'trace_id'  => $this->getTraceId(),
        ];

        if ($this->parentId !== null) {
            $payload['parent_id'] = $this->parentId;
        }

        return $payload;
    }
}
