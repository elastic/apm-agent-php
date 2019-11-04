<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests\Events;

use Elastic\Tests\TestCase;
use Elastic\Events\Event;

/**
 * @see Elastic\Events\Event
 */
final class EventTest extends TestCase
{

    /**
     * @covers Event::__construct
     * @covers Event::generateId
     */
    public function testInitRootEvent()
    {
        $event = new Event();

        $this->assertNull($event->getParentId());
    }

    /**
     * @depends testInitRootEvent
     *
     * @covers Event::setParent
     * @covers Event::getParentId
     * @covers Event::hasParent
     */
    public function testParentAssignment()
    {
        $parent = new Event();
        $child  = new Event();

        $child->setParent($parent);

        $this->assertNull($parent->getParentId());
        $this->assertNotNull($child->getParentId());

        $this->assertNotEquals($parent->getId(), $child->getId());
        $this->assertEquals($parent->getId(), $child->getParentId());
        $this->assertEquals($parent->getTraceId(), $child->getTraceId());

        $this->assertFalse($parent->hasParent());
        $this->assertTrue($child->hasParent());
    }

    /**
     * @depends testInitRootEvent
     *
     * @covers Event::__construct
     * @covers Event::generateId
     */
    public function testGenerationOfIds()
    {

        // TODO -- test the hex ids

        $this->assertTrue(true);
    }

    /**
     * @depends testGenerationOfIds
     *
     * @covers Event::__construct
     * @covers Event::generateId
     */
    public function testIdGenerationAndParentlessContructor()
    {
        $e1 = new Event();
        $e2 = new Event();

        $this->assertNotNull($e1->getId(), $e2->getId());
        $this->assertNotNull($e1->getTraceId(), $e2->getTraceId());
    }
}
