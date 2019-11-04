<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Events;

use JsonSerializable;

/**
 * Metricset Event
 *
 * @version Intake API v2
 * @see Elastic\Tests\Events\MetricsetTest
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class Metricset extends BaseEvent implements JsonSerializable
{

    /**
     * @var array
     */
    private $samples = [];

    /**
     * Add Sample
     *
     * @param string $key
     * @param number $value
     */
    final public function addSample(string $key, $value) : void
    {
        $this->samples[$key] = ['value' => $value];
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        return [
            'metricset' => [
                'samples'   => $this->samples,
                'timestamp' => $this->getTimestamp(),
            ]
        ];
    }
}
