<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests\Events;

use Elastic\Tests\TestCase;
use Elastic\Events\BaseEvent;

/**
 * @see Elastic\Events\BaseEvent
 */
final class BaseEventTest extends TestCase
{

    /**
     * @covers BaseEvent::__construct
     * @covers BaseEvent::getTimestamp
     */
    public function testTimestamp()
    {
        $now = $this->getTimestamp();
        usleep(5);
        $be = new BaseEvent();
        $et = $be->getTimestamp();

        $this->assertIsInt($be->getTimestamp());
        $this->assertGreaterThan($now, $be->getTimestamp());
        $this->assertEquals($et, $be->getTimestamp());

        usleep(5);
        $this->assertGreaterThan($be->getTimestamp(), $this->getTimestamp());
    }
}
