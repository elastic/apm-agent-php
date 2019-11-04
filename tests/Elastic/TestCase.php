<?php declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests;

/**
 *
 */
class TestCase extends \PHPUnit\Framework\TestCase
{

    /**
     * @var array
     */
    private $spec = [];

    /**
     * Load the Spec file into scope
     *
     * @param string $spec
     */
    protected function loadSpec(string $spec)
    {
        $path = sprintf('%s/../spec/%s.json', dirname(__DIR__), $spec);
        $content = file_get_contents($path);
        $spec = json_decode($content, true);
    }

    /**
     * Get the full spec array
     *
     * @return array
     */
    protected function getSpec() : array
    {
        return $this->spec;
    }

    /**
     * Get a now Timestamp with mircoseconds
     *
     * @return int
     */
    protected function getTimestamp() : int
    {
        return (int)round(microtime(true) * 1000000);
    }
}
