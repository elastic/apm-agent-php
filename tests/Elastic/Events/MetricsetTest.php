<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests\Events;

use Elastic\Tests\TestCase;
use Elastic\Events\Metricset;

/**
 * @see Elastic\Events\Metricset
 */
final class MetricsetTest extends TestCase
{

    /**
     * @covers Metricset::jsonSerialize
     * @covers Metricset::addSample
     */
    public function testBasicJsonSerialization()
    {
        $this->loadSpec('metricsets/metricset');

        $samples = [
            'foo' => 'bar',
            'hey' => rand(1, 999),
            'cpu.usage.real' => (rand(0, 100) / 100),
        ];

        $set = new Metricset();
        foreach ($samples as $k => $v) {
            $set->addSample($k, $v);
        }

        $arr = $set->jsonSerialize();

        $this->assertArrayHasKey('metricset', $arr);
        $this->assertEquals(count($arr), 1);

        $this->assertArrayHasKey('timestamp', $arr['metricset']);
        $this->assertGreaterThanOrEqual($arr['metricset']['timestamp'], $this->getTimestamp());

        $this->assertArrayHasKey('samples', $arr['metricset']);
        $this->assertNotEmpty($arr['metricset']['samples']);
        $this->assertEquals(count($arr['metricset']['samples']), count($samples));

        foreach ($samples as $k => $v) {
            $this->assertArrayHasKey($k, $arr['metricset']['samples']);
            $this->assertArrayHasKey('value', $arr['metricset']['samples'][$k]);
            $this->assertEquals($v, $arr['metricset']['samples'][$k]['value']);
        }
    }
}
