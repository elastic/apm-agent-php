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

use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\TestsSharedCode\EventsFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestArrayUtil;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TextUtilForTests;
use ElasticApmTests\Util\ValidationUtil;
use PHPUnit\Framework\TestCase;

final class DataFromAgent implements LoggableInterface
{
    use SerializedEventSinkTrait;
    use LoggableTrait;

    /** @var IntakeApiRequest[] */
    public $intakeApiRequests = [];

    /** @var EventsFromAgent */
    private $eventsFromAgent;

    /** @var Logger */
    private $logger;

    /** @var int */
    private $intakeApiRequestIndexStartOffset = 0;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->eventsFromAgent = new EventsFromAgent();
    }

    public function eventsFromAgent(): EventsFromAgent
    {
        return $this->eventsFromAgent;
    }

    /**
     * @return Metadata[]
     */
    public function metadata(): array
    {
        return $this->eventsFromAgent->metadatas;
    }

    /**
     * @return array<string, TransactionData>
     */
    public function idToTransaction(): array
    {
        return $this->eventsFromAgent->idToTransaction;
    }

    /**
     * @return array<string, SpanData>
     */
    public function idToSpan(): array
    {
        return $this->eventsFromAgent->idToSpan;
    }

    public function clearAdded(): void
    {
        $this->intakeApiRequestIndexStartOffset = count($this->intakeApiRequests);
        $this->intakeApiRequests = [];
        $this->eventsFromAgent->clear();
    }

    public function nextIntakeApiRequestIndexToFetch(): int
    {
        return $this->intakeApiRequestIndexStartOffset + count($this->intakeApiRequests);
    }

    /**
     * @param IntakeApiRequest[] $newIntakeApiRequests
     * @param float              $timeBeforeRequestToApp
     */
    public function addIntakeApiRequests(array $newIntakeApiRequests, float $timeBeforeRequestToApp): void
    {
        TestCase::assertNotEmpty($newIntakeApiRequests);

        foreach ($newIntakeApiRequests as $intakeApiRequest) {
            $this->processIntakeApiRequest($intakeApiRequest, $timeBeforeRequestToApp);
            $this->intakeApiRequests[] = $intakeApiRequest;
        }
    }

    private function processIntakeApiRequest(
        IntakeApiRequest $intakeApiRequest,
        float $timeBeforeRequestToApp
    ): void {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Processing intake API request',
            ['intakeApiRequestsBody' => $intakeApiRequest->body]
        );

        $isFirstLine = true;
        $encounteredEmptyLine = false;
        foreach (self::iterateLines($intakeApiRequest->body) as $bodyLine) {
            if (empty($bodyLine)) {
                $encounteredEmptyLine = true;
                continue;
            }
            // empty line can only be the last one
            ValidationUtil::assertThat(!$encounteredEmptyLine);

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Processing a line from intake API request',
                ['bodyLine' => $bodyLine]
            );

            $decodedJson = JsonUtil::decode($bodyLine, /* asAssocArray */ true);
            TestCase::assertCount(
                1,
                $decodedJson,
                'Each decoded line should have exactly one top level key.' . " bodyLine: `$bodyLine'"
            );
            $eventKind = array_key_first($decodedJson);
            $eventAsDecodedJson = $decodedJson[$eventKind];
            if ($isFirstLine) {
                ValidationUtil::assertThat($eventKind === 'metadata');
            }
            switch ($eventKind) {
                case 'error':
                    $this->processError($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                case 'metadata':
                    $this->processMetadata($eventAsDecodedJson);
                    break;
                case 'metricset':
                    $this->processMetricSet($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                case 'transaction':
                    $this->processTransaction($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                case 'span':
                    $this->processSpan($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                default:
                    TestCase::fail('Unexpected event kind `' . $eventKind . '\'.' . " bodyLine: `$bodyLine'");
            }
            $isFirstLine = false;
        }
    }

    /**
     * @param array<string, mixed> $decodedJson
     *
     * @return string
     */
    private static function decodedJsonToString(array $decodedJson): string
    {
        return JsonUtil::encode($decodedJson, /* prettyPrint: */ true);
    }

    /**
     * @param array<string, mixed> $metadataAsDecodedJson
     */
    private function processMetadata(array $metadataAsDecodedJson): void
    {
        $this->eventsFromAgent->metadatas[]
            = $this->validateAndDeserializeMetadata(self::decodedJsonToString($metadataAsDecodedJson));
    }

    /**
     * @param array<string, mixed> $transactionDecodedJson
     * @param IntakeApiRequest     $fromIntakeApiRequest
     * @param float                $timeBeforeRequestToApp
     */
    private function processTransaction(
        array $transactionDecodedJson,
        IntakeApiRequest $fromIntakeApiRequest,
        float $timeBeforeRequestToApp
    ): void {
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadatas));

        $newTransaction = $this->validateAndDeserializeTransactionData(
            self::decodedJsonToString($transactionDecodedJson)
        );

        TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newTransaction->timestamp);
        TestCaseBase::assertLessThanOrEqualTimestamp(
            TestCaseBase::calcEndTime($newTransaction),
            $fromIntakeApiRequest->timeReceivedAtServer
        );

        ValidationUtil::assertThat(is_null($this->executionSegmentByIdOrNull($newTransaction->id)));

        $this->eventsFromAgent->idToTransaction[$newTransaction->id] = $newTransaction;
    }

    /**
     * @param array<string, mixed> $spanDecodedJson
     * @param IntakeApiRequest     $fromIntakeApiRequest
     * @param float                $timeBeforeRequestToApp
     */
    private function processSpan(
        array $spanDecodedJson,
        IntakeApiRequest $fromIntakeApiRequest,
        float $timeBeforeRequestToApp
    ): void {
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadatas));

        $newSpan = $this->validateAndDeserializeSpanData(self::decodedJsonToString($spanDecodedJson));

        TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newSpan->timestamp);
        TestCaseBase::assertLessThanOrEqualTimestamp(
            TestCaseBase::calcEndTime($newSpan),
            $fromIntakeApiRequest->timeReceivedAtServer
        );

        ValidationUtil::assertThat(is_null($this->executionSegmentByIdOrNull($newSpan->id)));

        $this->eventsFromAgent->idToSpan[$newSpan->id] = $newSpan;
    }

    /**
     * @param array<string, mixed> $errorDecodedJson
     * @param IntakeApiRequest     $fromIntakeApiRequest
     * @param float                $timeBeforeRequestToApp
     */
    private function processError(
        array $errorDecodedJson,
        IntakeApiRequest $fromIntakeApiRequest,
        float $timeBeforeRequestToApp
    ): void {
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadatas));

        $newError = $this->validateAndDeserializeErrorData(self::decodedJsonToString($errorDecodedJson));

        TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newError->timestamp);
        TestCaseBase::assertLessThanOrEqualTimestamp($newError->timestamp, $fromIntakeApiRequest->timeReceivedAtServer);

        ValidationUtil::assertThat($this->errorByIdOrNull($newError->id) === null);

        $this->eventsFromAgent->idToError[$newError->id] = $newError;
    }

    /**
     * @param array<string, mixed> $metricSetDecodedJson
     * @param IntakeApiRequest     $fromIntakeApiRequest
     * @param float                $timeBeforeRequestToApp
     *
     * @noinspection PhpUnusedParameterInspection
     */
    private function processMetricSet(
        array $metricSetDecodedJson,
        IntakeApiRequest $fromIntakeApiRequest,
        float $timeBeforeRequestToApp
    ): void {
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadatas));

        // TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newEvent->timestamp);
        // TestCaseBase::assertLessThanOrEqualTimestamp(
        //     TestCaseBase::getEndTimestamp($newEvent),
        //     $fromRequest->timeReceivedAtServer
        // );
    }

    /**
     * @param string $text
     *
     * @return iterable<string>
     */
    private static function iterateLines(string $text): iterable
    {
        $prevPos = 0;
        $currentPos = $prevPos;
        $textLen = strlen($text);
        for (; $currentPos != $textLen;) {
            $endOfLineSeqLength = TextUtilForTests::ifEndOfLineSeqGetLength($text, $textLen, $currentPos);
            if ($endOfLineSeqLength === 0) {
                ++$currentPos;
                continue;
            }
            yield substr($text, $prevPos, $currentPos - $prevPos);
            $prevPos = $currentPos + $endOfLineSeqLength;
            $currentPos = $prevPos;
        }

        yield substr($text, $prevPos, $currentPos - $prevPos);
    }

    /**
     * @param array<mixed>  $idToEventData
     *
     * @return mixed
     *
     * @template        T
     * @phpstan-param   array<string, T> $idToEventData
     * @phpstan-return  T
     */
    private static function getSingleEvent(array $idToEventData)
    {
        TestCase::assertCount(1, $idToEventData);
        return TestArrayUtil::getFirstValue($idToEventData);
    }

    /**
     * @return TransactionData
     */
    public function singleTransaction(): TransactionData
    {
        return self::getSingleEvent($this->eventsFromAgent->idToTransaction);
    }

    /**
     * @return SpanData
     */
    public function singleSpan(): SpanData
    {
        return self::getSingleEvent($this->eventsFromAgent->idToSpan);
    }

    /**
     * @return ErrorData
     */
    public function singleError(): ErrorData
    {
        return self::getSingleEvent($this->eventsFromAgent->idToError);
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentData
    {
        if (!is_null($span = ArrayUtil::getValueIfKeyExistsElse($id, $this->eventsFromAgent->idToSpan, null))) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $this->eventsFromAgent->idToTransaction, null);
    }

    public function executionSegmentById(string $id): ExecutionSegmentData
    {
        $result = $this->executionSegmentByIdOrNull($id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }

    public function errorByIdOrNull(string $id): ?ErrorData
    {
        return ArrayUtil::getValueIfKeyExistsElse($id, $this->eventsFromAgent->idToError, null);
    }
}
