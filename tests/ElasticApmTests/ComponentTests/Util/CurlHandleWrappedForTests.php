<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\AutoInstrument\CurlHandleWrappedTrait;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use PHPUnit\Framework\TestCase;

final class CurlHandleWrappedForTests implements LoggableInterface
{
    use CurlHandleWrappedTrait;

    /** @var string */
    private $tempFileForVerboseOutput;

    /** @var string */
    private $lastVerboseOutput = '';

    /**
     * @param resource|object $curlHandle
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct(ResourcesClient $resourcesClient, $curlHandle)
    {
        $this->constructCurlHandleWrappedTrait($curlHandle);
        $this->tempFileForVerboseOutput = $resourcesClient->createTempFile('curl_verbose');
    }

    /**
     * @return string|bool
     */
    public function exec()
    {
        $this->setOpt(CURLOPT_VERBOSE, true);

        $assertMsg = LoggableToString::convert(['$this' => $this]);
        /** @var null|string|bool $retVal */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $retVal = null;
        /** @var null|resource|false $verboseOutputFile */
        $verboseOutputFile = null;
        $hasVerboseOutputWritten = false;
        try {
            $verboseOutputFile = fopen($this->tempFileForVerboseOutput, 'w'); // open file for write
            TestCase::assertIsResource($verboseOutputFile, $assertMsg);
            $this->setOpt(CURLOPT_STDERR, $verboseOutputFile);
            $retVal = curl_exec($this->curlHandle); // @phpstan-ignore-line
        } finally {
            if ($verboseOutputFile !== null) {
                TestCase::assertIsResource($verboseOutputFile, $assertMsg);
                TestCase::assertTrue(fflush($verboseOutputFile), $assertMsg);
                TestCase::assertTrue(fclose($verboseOutputFile), $assertMsg);
                $verboseOutputFile = null;
                $hasVerboseOutputWritten = true;
            }
        }

        if ($hasVerboseOutputWritten) {
            $verboseOutput = file_get_contents($this->tempFileForVerboseOutput);
            TestCase::assertIsString($verboseOutput, $assertMsg);
            $this->lastVerboseOutput = $verboseOutput;
        }

        TestCase::assertNotNull($retVal, $assertMsg);
        return $retVal;
    }

    public function error(): string
    {
        return curl_error($this->curlHandle); // @phpstan-ignore-line
    }

    public function errno(): int
    {
        return curl_errno($this->curlHandle); // @phpstan-ignore-line
    }

    public function verboseOutput(): string
    {
        return $this->lastVerboseOutput;
    }

    public function close(): void
    {
        curl_close($this->curlHandle); // @phpstan-ignore-line
    }
}
