<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Events;

use Throwable;

/**
 * Error Event
 *
 * @version Intake API v2
 * @see Elastic\Tests\Events\ErrorTest
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class Error extends Event
{

    /**
     * @var string|null
     */
    private $culprit = null;

    /**
     * @var string|null
     */
    private $transactionId = null;

    /**
     * @var array
     */
    private $transaction = [];

    /**
     * @var Throwable|null
     */
    private $throwable = null;

    /**
     * Init the Error with 128 bit Id
     */
    public function __construct()
    {
        parent::__construct(128);
    }

    /**
     * @see Event::setParent
     */
    public function setParent(Event $parent) : void
    {
        parent::setParent($parent);
        // Set the Transaction details if available
        if ($parent instanceof Transaction) {
            $this->transactionId = $parent->getId();
            $transaction = [
                'sampled' => $parent->isSampled(),
                'type'    => $parent->getType(),
            ];
        }
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        $payload = $this->getBasePayload();

        if ($this->transactionId !== null) {
            $payload['transaction_id'] = $this->transactionId;
        }

        if (empty($this->transaction) === false) {
            $payload['transaction'] = $this->transaction;
        }

        if ($this->culprit !== null) {
            $payload['culprit'] = $this->culprit;
        }


        // TODO add Context

        // TODO add Log

        if ($this->throwable !== null) {
            $payload['exception'] = [
                'code'       => $this->throwable->getCode(),
                'message'    => $this->throwable->getMessage(),
//                'module'     => sprintf('%s:%d', basename($this->throwable->getFile()), $this->throwable->getLine()),
//                'attributes' => [],
//                'stacktrace' => [],
                'type'       => $this->throwable->__toString(),
//                'handled'    => false,
            ];
        }
    //    var_dump($payload);
        return [ 'error' => $payload ];
    }

    /**
     * @return ?string
     */
    final public function getTransactionId() : ?string
    {
        return $this->transactionId;
    }

    /**
     * @param string
     */
    final public function setCulprit(string $culprit) : void
    {
        $this->culprit = trim($culprit);
    }

    /**
     * @return ?string
     */
    final public function getCulprit() : ?string
    {
        return $this->culprit;
    }

    /**
     * @param Throwable $t
     */
    final public function setThrowable(Throwable $t) : void
    {
        $this->throwable = $t;
    }

    /**
     * @return Throwable|null
     */
    final public function getThrowable() : ?Throwable
    {
        return $this->throwable;
    }
}
