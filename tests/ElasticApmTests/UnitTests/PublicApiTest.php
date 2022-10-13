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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\TransactionContextRequest;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\DataProviderForTestBuilder;

class PublicApiTest extends TracerUnitTestCaseBase
{
    public function testBeginEndTransaction(): void
    {
        // Arrange
        $this->assertFalse($this->tracer->isNoop());

        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertFalse($tx->isNoop());
        $tx->end();

        // Assert
        $this->assertEmpty($this->mockEventSink->idToSpan());
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->name);
        $this->assertSame('test_TX_type', $reportedTx->type);
    }

    public function testBeginEndSpan(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span_1 = $tx->beginChildSpan('test_span_1_name', 'test_span_1_type');
        // spans can overlap in any desired way
        $span_2 = $tx->beginChildSpan(
            'test_span_2_name',
            'test_span_2_type',
            'test_span_2_subtype',
            'test_span_2_action'
        );
        $span_2_1 = $span_2->beginChildSpan('test_span_2_1_name', 'test_span_2_1_type', 'test_span_2_1_subtype');
        $span_2_2 = $span_2->beginChildSpan(
            'test_span_2_2_name',
            'test_span_2_2_type',
            /* subtype: */ null,
            'test_span_2_2_action'
        );
        $span_1->end();
        $span_2_2->end();
        // parent span can end before its child spans
        $span_2->end();
        $span_2_1->end();
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->name);
        $this->assertSame('test_TX_type', $reportedTx->type);

        $this->assertCount(4, $this->mockEventSink->idToSpan());

        $reportedSpan_1 = $this->mockEventSink->singleSpanByName('test_span_1_name');
        $this->assertSame('test_span_1_type', $reportedSpan_1->type);
        $this->assertNull($reportedSpan_1->subtype);
        $this->assertNull($reportedSpan_1->action);

        $reportedSpan_2 = $this->mockEventSink->singleSpanByName('test_span_2_name');
        $this->assertSame('test_span_2_type', $reportedSpan_2->type);
        $this->assertSame('test_span_2_subtype', $reportedSpan_2->subtype);
        $this->assertSame('test_span_2_action', $reportedSpan_2->action);

        $reportedSpan_2_1 = $this->mockEventSink->singleSpanByName('test_span_2_1_name');
        $this->assertSame('test_span_2_1_type', $reportedSpan_2_1->type);
        $this->assertSame('test_span_2_1_subtype', $reportedSpan_2_1->subtype);
        $this->assertNull($reportedSpan_2_1->action);

        $reportedSpan_2_2 = $this->mockEventSink->singleSpanByName('test_span_2_2_name');
        $this->assertSame('test_span_2_2_type', $reportedSpan_2_2->type);
        $this->assertNull($reportedSpan_2_2->subtype);
        $this->assertSame('test_span_2_2_action', $reportedSpan_2_2->action);

        $this->assertCount(0, $this->mockEventSink->findSpansByName('nonexistent_test_span_name'));
    }

    public function testTransactionSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name_1', 'test_TX_type');
        $tx->setName('test_TX_name_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name_2', $reportedTx->name);
    }

    public function testTransactionSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $tx->setType('test_TX_type_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_type_2', $reportedTx->type);
    }

    public function testTransactionSetResult(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_result', $reportedTx->result);
    }

    public function testTransactionSetResultToNull(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->setResult(null);
        $this->assertSame(null, $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame(null, $reportedTx->result);
    }

    public function testSpanSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name_1', 'test_span_type');
        $span->setName('test_span_name_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_name_2', $reportedSpan->name);
    }

    public function testSpanSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type_1');
        $span->setType('test_span_type_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_type_2', $reportedSpan->type);
    }

    public function testSpanSetSubtype(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setSubtype('test_span_subtype');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_subtype', $reportedSpan->subtype);
    }

    public function testSpanSetAction(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setAction('test_span_action');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_action', $reportedSpan->action);
    }

    public function testGeneratedIds(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->end();
        $tx->end();

        // Assert
        $this->assertValidTransactionAndSpans(
            $this->mockEventSink->singleTransaction(),
            $this->mockEventSink->idToSpan()
        );
    }

    public function testVersionShouldNotBeEmpty(): void
    {
        $this->assertTrue(strlen(ElasticApm::VERSION) != 0);
    }

    public function testSpanContextDestinationService(): void
    {
        foreach ([true, false] as $shouldSet) {
            $this->mockEventSink->clear();

            // Act
            $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
            $span = $tx->beginChildSpan('test_span_name', 'test_span_type');

            if ($shouldSet) {
                $span->context()->destination()->setService(
                    'test_span_destination_service_name',
                    'test_span_destination_service_resource',
                    'test_span_destination_service_type'
                );
            } else {
                // Access context.destination without setting anything to verify that it's sent only when not empty
                $span->context()->destination();
            }

            $span->end();
            $tx->end();

            // Assert
            $spanData = $this->mockEventSink->singleSpan();

            if ($shouldSet) {
                self::assertNotNull($spanData->context);
                self::assertNotNull($spanData->context->destination);
                self::assertNotNull($spanData->context->destination->service);
                self::assertSame('test_span_destination_service_name', $spanData->context->destination->service->name);
                self::assertSame(
                    'test_span_destination_service_resource',
                    $spanData->context->destination->service->resource
                );
                self::assertSame('test_span_destination_service_type', $spanData->context->destination->service->type);
            } else {
                self::assertNull($spanData->context);
            }
        }
    }

    public function testSpanContextDb(): void
    {
        foreach ([true, false] as $shouldSet) {
            $this->mockEventSink->clear();

            // Act
            $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
            $span = $tx->beginChildSpan('test_span_name', 'test_span_type');

            if ($shouldSet) {
                $span->context()->db()->setStatement('test span ctx DB statement');
            } else {
                // Access context.db without setting anything to verify that it's sent only when not empty
                $span->context()->db();
            }

            $span->end();
            $tx->end();

            // Assert
            $spanData = $this->mockEventSink->singleSpan();

            if ($shouldSet) {
                self::assertNotNull($spanData->context);
                self::assertNotNull($spanData->context->db);
                self::assertSame('test span ctx DB statement', $spanData->context->db->statement);
            } else {
                self::assertNull($spanData->context);
            }
        }
    }

    public function testTransactionContextRequest(): void
    {
        foreach ([true, false] as $shouldSetMethod) {
            foreach ([true, false] as $shouldSetUrlDomain) {
                foreach ([true, false] as $shouldSetUrlFull) {
                    foreach ([true, false] as $shouldSetUrlOriginal) {
                        foreach ([true, false] as $shouldSetUrlPath) {
                            foreach ([true, false] as $shouldSetUrlPort) {
                                foreach ([true, false] as $shouldSetUrlProtocol) {
                                    foreach ([true, false] as $shouldSetUrlQuery) {
                                        $this->mockEventSink->clear();

                                        $this->implTestTransactionContextRequest(
                                            $shouldSetMethod,
                                            $shouldSetUrlDomain,
                                            $shouldSetUrlFull,
                                            $shouldSetUrlOriginal,
                                            $shouldSetUrlPath,
                                            $shouldSetUrlPort,
                                            $shouldSetUrlProtocol,
                                            $shouldSetUrlQuery
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function implTestTransactionContextRequest(
        bool $shouldSetMethod,
        bool $shouldSetUrlDomain,
        bool $shouldSetUrlFull,
        bool $shouldSetUrlOriginal,
        bool $shouldSetUrlPath,
        bool $shouldSetUrlPort,
        bool $shouldSetUrlProtocol,
        bool $shouldSetUrlQuery
    ): void {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');

        // Dummy call to test that nothing is serialized when nothing is set
        $tx->context()->request()->url();

        $method = self::generateDummyMaxKeywordString('my HTTP method');
        if ($shouldSetMethod) {
            $tx->context()->request()->setMethod($method . 'suffix that will be cut off');
        }

        $urlDomain = self::generateDummyMaxKeywordString('my URL domain');
        if ($shouldSetUrlDomain) {
            $tx->context()->request()->url()->setDomain($urlDomain . 'suffix that will be cut off');
        }

        $urlFull = self::generateDummyMaxKeywordString('my full URL');
        if ($shouldSetUrlFull) {
            $tx->context()->request()->url()->setFull($urlFull . 'suffix that will be cut off');
        }

        $urlOriginal = self::generateDummyMaxKeywordString('my original URL');
        if ($shouldSetUrlOriginal) {
            $tx->context()->request()->url()->setOriginal($urlOriginal . 'suffix that will be cut off');
        }

        $urlPath = self::generateDummyMaxKeywordString('my URL path');
        if ($shouldSetUrlPath) {
            $tx->context()->request()->url()->setPath($urlPath . 'suffix that will be cut off');
        }

        $urlPort = 54321;
        if ($shouldSetUrlPort) {
            $tx->context()->request()->url()->setPort($urlPort);
        }

        $urlProtocol = self::generateDummyMaxKeywordString('my URL protocol');
        if ($shouldSetUrlProtocol) {
            $tx->context()->request()->url()->setProtocol($urlProtocol . 'suffix that will be cut off');
        }

        $urlQuery = self::generateDummyMaxKeywordString('my URL query');
        if ($shouldSetUrlQuery) {
            $tx->context()->request()->url()->setQuery($urlQuery . 'suffix that will be cut off');
        }

        $tx->end();

        // Assert
        $txData = $this->mockEventSink->singleTransaction();

        if (
            !$shouldSetMethod
            && !$shouldSetUrlDomain
            && !$shouldSetUrlFull
            && !$shouldSetUrlOriginal
            && !$shouldSetUrlPath
            && !$shouldSetUrlPort
            && !$shouldSetUrlProtocol
            && !$shouldSetUrlQuery
        ) {
            self::assertNull($txData->context);
            return;
        }

        self::assertNotNull($txData->context);
        self::assertNotNull($txData->context->request);

        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        self::assertNotNull($txData->context->request->method);
        self::assertNotNull($txData->context->request->url);

        if ($shouldSetMethod) {
            self::assertSame($method, $txData->context->request->method);
        } else {
            self::assertSame(TransactionContextRequest::UNKNOWN_METHOD, $txData->context->request->method);
        }

        if ($shouldSetUrlDomain) {
            self::assertSame($urlDomain, $txData->context->request->url->domain);
        } else {
            self::assertNull($txData->context->request->url->domain);
        }

        if ($shouldSetUrlFull) {
            self::assertSame($urlFull, $txData->context->request->url->full);
        } else {
            self::assertNull($txData->context->request->url->full);
        }

        if ($shouldSetUrlOriginal) {
            self::assertSame($urlOriginal, $txData->context->request->url->original);
        } else {
            self::assertNull($txData->context->request->url->original);
        }

        if ($shouldSetUrlPath) {
            self::assertSame($urlPath, $txData->context->request->url->path);
        } else {
            self::assertNull($txData->context->request->url->path);
        }

        if ($shouldSetUrlPort) {
            self::assertSame($urlPort, $txData->context->request->url->port);
        } else {
            self::assertNull($txData->context->request->url->port);
        }

        if ($shouldSetUrlProtocol) {
            self::assertSame($urlProtocol, $txData->context->request->url->protocol);
        } else {
            self::assertNull($txData->context->request->url->protocol);
        }

        if ($shouldSetUrlQuery) {
            self::assertSame($urlQuery, $txData->context->request->url->query);
        } else {
            self::assertNull($txData->context->request->url->query);
        }
    }

    /**
     * @return iterable<array<?string>>
     */
    public function dataProviderForSetOutcome(): iterable
    {
        $validValues = [null, Constants::OUTCOME_SUCCESS, Constants::OUTCOME_FAILURE, Constants::OUTCOME_UNKNOWN];
        foreach ($validValues as $validValue) {
            yield [$validValue, $validValue];
        }

        $invalidValues = ['', 'dummy', Constants::OUTCOME_SUCCESS . '1', '2' . Constants::OUTCOME_FAILURE];
        foreach ($invalidValues as $invalidValue) {
            yield [$invalidValue, null];
        }
    }

    /**
     * @dataProvider dataProviderForSetOutcome
     *
     * @param ?string $valueToSet
     * @param ?string $expected
     */
    public function testTransactionSetOutcome(?string $valueToSet, ?string $expected): void
    {
        // Act

        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->setOutcome($valueToSet);
        $tx->end();

        // Assert

        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame($expected, $reportedTx->outcome);
    }

    /**
     * @dataProvider dataProviderForSetOutcome
     *
     * @param ?string $valueToSet
     * @param ?string $expected
     */
    public function testSpanSetOutcome(?string $valueToSet, ?string $expected): void
    {
        // Act

        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setOutcome($valueToSet);
        $span->end();
        $tx->end();

        // Assert

        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame($expected, $reportedSpan->outcome);
    }

    /**
     * @return iterable<array{null|int|string, ?string, ?string}>
     */
    public function dataProviderForTestTransactionSetUserContext(): iterable
    {
        /** @var iterable<array{null|int|string, ?string, ?string}> $result */
        $result = (new DataProviderForTestBuilder())
            ->addDimensionOnlyFirstValueCombinable([123, 'test_user_id', null, '', 0])
            ->addDimensionOnlyFirstValueCombinable(['test_user_email', null, ''])
            ->addDimensionOnlyFirstValueCombinable(['test_user_username', null, ''])
            ->build();
        return $result;
    }

    /**
     * @dataProvider dataProviderForTestTransactionSetUserContext
     *
     * @param null|int|string $id
     * @param ?string         $email
     * @param ?string         $username
     */
    public function testTransactionSetUserContext($id, ?string $email, ?string $username): void
    {
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->context()->user()->setId($id);
        $tx->context()->user()->setEmail($email);
        $tx->context()->user()->setUsername($username);
        $tx->end();

        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertNotNull($reportedTx->context);
        $this->assertNotNull($reportedTx->context->user);
        $this->assertSame($id, $reportedTx->context->user->id);
        $this->assertSame($email, $reportedTx->context->user->email);
        $this->assertSame($username, $reportedTx->context->user->username);
    }
}
