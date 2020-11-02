<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\TestsSharedCode\EventsFromAgent;
use Elastic\Apm\Tests\Util\Deserialization\SerializedEventSinkTrait;
use Elastic\Apm\Tests\Util\LogCategoryForTests;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\Tests\Util\ValidationUtil;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DataFromAgent
{
    use SerializedEventSinkTrait;
    use ObjectToStringUsingPropertiesTrait;

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
     * @return MetadataInterface[]
     */
    public function metadata(): array
    {
        return $this->eventsFromAgent->metadata;
    }

    /**
     * @return array<string, TransactionDataInterface>
     */
    public function idToTransaction(): array
    {
        return $this->eventsFromAgent->idToTransaction;
    }

    /**
     * @return array<string, SpanDataInterface>
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

            $decodedJson = json_decode($bodyLine, /* assoc */ true);
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
                case 'metadata':
                    $this->processMetadata($eventAsDecodedJson);
                    break;
                case 'transaction':
                    $this->processTransaction($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                case 'span':
                    $this->processSpan($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
                    break;
                case 'metricset':
                    $this->processMetricSet($eventAsDecodedJson, $intakeApiRequest, $timeBeforeRequestToApp);
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
        $encodedJson = json_encode($decodedJson, JSON_PRETTY_PRINT);
        if ($encodedJson === false) {
            throw new RuntimeException(
                'json_encode failed. json_last_error_msg(): ' . json_last_error_msg()
            );
        }
        return $encodedJson;
    }

    /**
     * @param array<string, mixed> $metadataAsDecodedJson
     */
    private function processMetadata(array $metadataAsDecodedJson): void
    {
        $this->eventsFromAgent->metadata[]
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
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadata));

        $newTransaction = $this->validateAndDeserializeTransactionData(
            self::decodedJsonToString($transactionDecodedJson)
        );

        TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newTransaction->getTimestamp());
        TestCaseBase::assertLessThanOrEqualTimestamp(
            TestCaseBase::calcEndTime($newTransaction),
            $fromIntakeApiRequest->timeReceivedAtServer
        );

        ValidationUtil::assertThat(is_null($this->executionSegmentByIdOrNull($newTransaction->getId())));

        $this->eventsFromAgent->idToTransaction[$newTransaction->getId()] = $newTransaction;
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
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadata));

        $newSpan = $this->validateAndDeserializeSpanData(self::decodedJsonToString($spanDecodedJson));

        TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newSpan->getTimestamp());
        TestCaseBase::assertLessThanOrEqualTimestamp(
            TestCaseBase::calcEndTime($newSpan),
            $fromIntakeApiRequest->timeReceivedAtServer
        );

        ValidationUtil::assertThat(is_null($this->executionSegmentByIdOrNull($newSpan->getId())));

        $this->eventsFromAgent->idToSpan[$newSpan->getId()] = $newSpan;
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
        ValidationUtil::assertThat(!empty($this->eventsFromAgent->metadata));

        // TestCaseBase::assertLessThanOrEqualTimestamp($timeBeforeRequestToApp, $newEvent->getTimestamp());
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
            $endOfLineSeqLength = TextUtil::ifEndOfLineSeqGetLength($text, $textLen, $currentPos);
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
     * @return TransactionDataInterface
     */
    public function singleTransaction(): TransactionDataInterface
    {
        TestCase::assertCount(1, $this->eventsFromAgent->idToTransaction);
        return $this->eventsFromAgent->idToTransaction[array_key_first($this->eventsFromAgent->idToTransaction)];
    }

    /**
     * @return SpanDataInterface
     */
    public function singleSpan(): SpanDataInterface
    {
        TestCase::assertCount(1, $this->eventsFromAgent->idToSpan);
        return $this->eventsFromAgent->idToSpan[array_key_first($this->eventsFromAgent->idToSpan)];
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentDataInterface
    {
        if (!is_null($span = ArrayUtil::getValueIfKeyExistsElse($id, $this->eventsFromAgent->idToSpan, null))) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $this->eventsFromAgent->idToTransaction, null);
    }

    public function executionSegmentById(string $id): ExecutionSegmentDataInterface
    {
        $result = $this->executionSegmentByIdOrNull($id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }
}
