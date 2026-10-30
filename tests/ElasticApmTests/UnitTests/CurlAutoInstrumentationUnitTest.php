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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\CurlAutoInstrumentation;
use Elastic\Apm\Impl\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\UnitTests\Util\MockLogSink;
use ElasticApmTests\UnitTests\Util\MockLogSinkStatement;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPGET;
use const CURLOPT_NOBODY;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PUT;
use const CURLOPT_URL;

/**
 * Drives CurlAutoInstrumentation through the same pre-hook -> actual call -> post-hook sequence
 * the extension uses for intercepted functions, without sending any requests over the network.
 *
 * Expected HTTP methods match what libcurl actually sends for the same sequence of options.
 */
final class CurlAutoInstrumentationUnitTest extends TracerUnitTestCaseBase
{
    private const HOST = 'example.com';
    private const URL = 'http://' . self::HOST . '/some/path';

    /** @var MockLogSink */
    private $mockLogSink;

    /** @var array<string, callable(mixed[]): ?callable> */
    private $funcNameToPreHook;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->mockLogSink = new MockLogSink();
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder): void {
                $builder->withLogSink($this->mockLogSink)->withConfig(OptionNames::LOG_LEVEL, 'WARNING');
            }
        );

        $registrationCtx = new class implements RegistrationContextInterface {
            /** @var array<string, callable(mixed[]): ?callable> */
            public $funcNameToPreHook = [];

            public function interceptCallsToInternalMethod(string $className, string $methodName, callable $preHook): void
            {
                TracerUnitTestCaseBase::fail('Unexpected call to ' . __FUNCTION__ . ' for ' . $className . '::' . $methodName);
            }

            public function interceptCallsToInternalFunction(string $functionName, callable $preHook): void
            {
                $this->funcNameToPreHook[$functionName] = $preHook;
            }
        };
        $tracer = $this->tracer;
        self::assertInstanceOf(Tracer::class, $tracer);
        (new CurlAutoInstrumentation($tracer))->register($registrationCtx);
        $this->funcNameToPreHook = $registrationCtx->funcNameToPreHook;
    }

    /**
     * @param string  $funcName
     * @param mixed[] $args
     *
     * @return mixed
     */
    private function callIntercepted(string $funcName, array $args)
    {
        self::assertArrayHasKey($funcName, $this->funcNameToPreHook);
        self::assertIsCallable($funcName);
        $postHook = ($this->funcNameToPreHook[$funcName])($args);
        // HTTP method is captured in curl_exec's pre-hook so there is no need to actually send the request
        $retVal = ($funcName === 'curl_exec') ? true : call_user_func_array($funcName, $args);
        if ($postHook !== null) {
            $postHook(/* numberOfStackFramesToSkip */ 0, /* hasExitedByException */ false, $retVal);
        }
        return $retVal;
    }

    /**
     * @param string                    $setOptFunc
     * @param mixed                     $curlHandle
     * @param array<array{int, mixed}>  $options
     */
    private function setOptions(string $setOptFunc, $curlHandle, array $options): void
    {
        if ($setOptFunc === 'curl_setopt') {
            foreach ($options as [$optionId, $optionValue]) {
                self::assertTrue($this->callIntercepted('curl_setopt', [$curlHandle, $optionId, $optionValue]));
            }
            return;
        }

        // An option can be set more than once (for example to reset it) but an array has unique keys
        // so start a new curl_setopt_array call whenever an option repeats to preserve the order
        /** @var array<array<int, mixed>> $batches */
        $batches = [[]];
        foreach ($options as [$optionId, $optionValue]) {
            if (array_key_exists($optionId, $batches[count($batches) - 1])) {
                $batches[] = [];
            }
            $batches[count($batches) - 1][$optionId] = $optionValue;
        }
        foreach ($batches as $batch) {
            self::assertTrue($this->callIntercepted('curl_setopt_array', [$curlHandle, $batch]));
        }
    }

    /**
     * @param mixed $curlHandle
     */
    private function execInTransaction($curlHandle): void
    {
        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $this->callIntercepted('curl_exec', [$curlHandle]);
        $tx->end();
    }

    /**
     * @return string[]
     */
    private function spanNameAndHttpMethodPairs(): array
    {
        $result = [];
        foreach ($this->mockEventSink->idToSpan() as $span) {
            self::assertNotNull($span->context);
            self::assertNotNull($span->context->http);
            $result[] = $span->name . ' / ' . $span->context->http->method;
        }
        sort($result);
        return $result;
    }

    /**
     * @return MockLogSinkStatement[]
     */
    private function loggedWarningsOrWorse(): array
    {
        return array_values(
            array_filter(
                $this->mockLogSink->consumed,
                function (MockLogSinkStatement $statement): bool {
                    return $statement->statementLevel <= LogLevel::WARNING;
                }
            )
        );
    }

    private function assertNothingLoggedAtWarningOrWorse(): void
    {
        $messages = array_map(
            function (MockLogSinkStatement $statement): string {
                return $statement->message;
            },
            $this->loggedWarningsOrWorse()
        );
        self::assertSame([], $messages);
    }

    /**
     * @return iterable<string, array{string, array<array{int, mixed}>, string}>
     */
    public function dataProviderForTestHttpMethod(): iterable
    {
        $cases = [
            // Methods derived from options other than CURLOPT_CUSTOMREQUEST
            'default'                => [[], 'GET'],
            'POST'                   => [[[CURLOPT_POST, true]], 'POST'],
            'POST then HTTPGET'      => [[[CURLOPT_POST, true], [CURLOPT_HTTPGET, true]], 'GET'],
            'POSTFIELDS'             => [[[CURLOPT_POSTFIELDS, 'a=b']], 'POST'],
            'PUT'                    => [[[CURLOPT_PUT, true]], 'PUT'],
            'NOBODY'                 => [[[CURLOPT_NOBODY, true]], 'HEAD'],
            'NOBODY set then unset'  => [[[CURLOPT_NOBODY, true], [CURLOPT_NOBODY, false]], 'GET'],

            'CUSTOMREQUEST DELETE'           => [[[CURLOPT_CUSTOMREQUEST, 'DELETE']], 'DELETE'],
            'CUSTOMREQUEST null'             => [[[CURLOPT_CUSTOMREQUEST, null]], 'GET'],
            'CUSTOMREQUEST DELETE then null' => [[[CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_CUSTOMREQUEST, null]], 'GET'],
            // The way Guzzle 7.16+ sends HEAD requests
            'CUSTOMREQUEST null, NOBODY'     => [[[CURLOPT_CUSTOMREQUEST, null], [CURLOPT_NOBODY, true]], 'HEAD'],

            // Resetting custom request goes back to the method derived from the other options - not necessarily GET
            'POST, CUSTOMREQUEST DELETE then null'
                => [[[CURLOPT_POST, true], [CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_CUSTOMREQUEST, null]], 'POST'],
            'POSTFIELDS, CUSTOMREQUEST PUT then null'
                => [[[CURLOPT_POSTFIELDS, 'a=b'], [CURLOPT_CUSTOMREQUEST, 'PUT'], [CURLOPT_CUSTOMREQUEST, null]], 'POST'],
            'PUT, CUSTOMREQUEST PATCH then null'
                => [[[CURLOPT_PUT, true], [CURLOPT_CUSTOMREQUEST, 'PATCH'], [CURLOPT_CUSTOMREQUEST, null]], 'PUT'],
            'NOBODY, CUSTOMREQUEST DELETE then null'
                => [[[CURLOPT_NOBODY, true], [CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_CUSTOMREQUEST, null]], 'HEAD'],
            'CUSTOMREQUEST PATCH, POST, CUSTOMREQUEST null'
                => [[[CURLOPT_CUSTOMREQUEST, 'PATCH'], [CURLOPT_POST, true], [CURLOPT_CUSTOMREQUEST, null]], 'POST'],

            // Custom request takes precedence regardless of the order in which the options were set
            // (CUSTOMREQUEST followed by POSTFIELDS is the way Guzzle sends requests with a body)
            'CUSTOMREQUEST PUT, POSTFIELDS'     => [[[CURLOPT_CUSTOMREQUEST, 'PUT'], [CURLOPT_POSTFIELDS, 'a=b']], 'PUT'],
            'CUSTOMREQUEST DELETE, POST'        => [[[CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_POST, true]], 'DELETE'],
            'CUSTOMREQUEST DELETE, HTTPGET'     => [[[CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_HTTPGET, true]], 'DELETE'],
            'CUSTOMREQUEST DELETE, NOBODY'      => [[[CURLOPT_CUSTOMREQUEST, 'DELETE'], [CURLOPT_NOBODY, true]], 'DELETE'],
            'CUSTOMREQUEST HEAD, NOBODY unset'  => [[[CURLOPT_CUSTOMREQUEST, 'HEAD'], [CURLOPT_NOBODY, false]], 'HEAD'],
        ];

        foreach (['curl_setopt', 'curl_setopt_array'] as $setOptFunc) {
            foreach ($cases as $desc => [$options, $expectedHttpMethod]) {
                yield $setOptFunc . ': ' . $desc => [$setOptFunc, $options, $expectedHttpMethod];
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestHttpMethod
     *
     * @param string                    $setOptFunc
     * @param array<array{int, mixed}>  $options
     * @param string                    $expectedHttpMethod
     */
    public function testHttpMethod(string $setOptFunc, array $options, string $expectedHttpMethod): void
    {
        $curlHandle = $this->callIntercepted('curl_init', [self::URL]);
        $this->setOptions($setOptFunc, $curlHandle, $options);
        $this->execInTransaction($curlHandle);

        self::assertSame([$expectedHttpMethod . ' ' . self::HOST . ' / ' . $expectedHttpMethod], $this->spanNameAndHttpMethodPairs());
        $this->assertNothingLoggedAtWarningOrWorse();
    }

    public function testCustomRequestOfUnexpectedTypeIsIgnored(): void
    {
        $curlHandle = $this->callIntercepted('curl_init', [self::URL]);
        $this->callIntercepted('curl_setopt', [$curlHandle, CURLOPT_POST, true]);
        // PHP converts it to "123" but only string and null are expected
        $this->callIntercepted('curl_setopt', [$curlHandle, CURLOPT_CUSTOMREQUEST, 123]);
        $this->execInTransaction($curlHandle);

        self::assertSame(['POST ' . self::HOST . ' / POST'], $this->spanNameAndHttpMethodPairs());
        $logged = $this->loggedWarningsOrWorse();
        self::assertCount(1, $logged);
        self::assertSame(LogLevel::ERROR, $logged[0]->statementLevel);
        self::assertSame('Actual type does not match the expected type', $logged[0]->message);
    }

    public function testCopiedHandleHasItsOwnCustomRequest(): void
    {
        $originalHandle = $this->callIntercepted('curl_init', [self::URL]);
        $this->callIntercepted('curl_setopt', [$originalHandle, CURLOPT_POST, true]);
        $this->callIntercepted('curl_setopt', [$originalHandle, CURLOPT_CUSTOMREQUEST, 'DELETE']);

        $copiedHandle = $this->callIntercepted('curl_copy_handle', [$originalHandle]);
        $this->callIntercepted('curl_setopt', [$copiedHandle, CURLOPT_URL, 'http://example.org/']);
        $this->callIntercepted('curl_setopt', [$copiedHandle, CURLOPT_CUSTOMREQUEST, null]);

        $this->execInTransaction($originalHandle);
        $this->execInTransaction($copiedHandle);

        self::assertSame(['DELETE example.com / DELETE', 'POST example.org / POST'], $this->spanNameAndHttpMethodPairs());
        $this->assertNothingLoggedAtWarningOrWorse();
    }
}
