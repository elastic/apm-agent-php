<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests\Events;

use Elastic\Tests\TestCase;
use Elastic\Events\Error;
use Elastic\Events\Transaction;

/**
 * @see Elastic\Events\Error
 */
final class ErrorTest extends TestCase
{

    /**
     * @covers Error::jsonSerialize
     * @covers Event::getBasePayload
     */
    public function testBasicJsonSerialization()
    {
    //    $this->loadSpec('metricsets/metricset');
        $arr = (new Error())->jsonSerialize();

        $this->assertArrayHasKey('error', $arr);
        $this->assertEquals(count($arr), 1);
    }

    /**
     * @depends testBasicJsonSerialization
     *
     * @covers Error::jsonSerialize
     * @covers Event::getBasePayload
     */
    public function testEventCommonPayload()
    {
        $e = new Error();
        $arr = $e->jsonSerialize();

        $this->assertArrayHasKey('timestamp', $arr['error']);
        $this->assertEquals($arr['error']['timestamp'], $e->getTimestamp());

        $this->assertArrayHasKey('id', $arr['error']);
        $this->assertEquals($arr['error']['id'], $e->getId());

        $this->assertArrayHasKey('trace_id', $arr['error']);
        $this->assertEquals($arr['error']['trace_id'], $e->getTraceId());

        $this->assertArrayNotHasKey('parent_id', $arr['error']);
        $this->assertArrayNotHasKey('transaction_id', $arr['error']);
    }

    /**
     * @depends testEventCommonPayload
     *
     * @covers Error::jsonSerialize
     * @covers Event::getBasePayload
     * @covers Event::setParent
     * @covers Error::setParent
     */
    public function testSetParentAndTransactionRelation()
    {
        $trx = new Transaction();
        $err = new Error();
        $err->setParent($trx);

        $arr = $err->jsonSerialize();

        $this->assertArrayHasKey('parent_id', $arr['error']);
        $this->assertEquals($arr['error']['parent_id'], $trx->getId());

        $this->assertArrayHasKey('transaction_id', $arr['error']);
        $this->assertEquals($arr['error']['transaction_id'], $trx->getId());
        $this->assertEquals($arr['error']['transaction_id'], $err->getTransactionId());
    }
}
