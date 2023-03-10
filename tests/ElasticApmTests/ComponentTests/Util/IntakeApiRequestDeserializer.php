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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TextUtilForTests;
use PHPUnit\Framework\TestCase;

final class IntakeApiRequestDeserializer implements LoggableInterface
{
    use LoggableTrait;
    use SerializedEventSinkTrait;

    /** @var IntakeApiRequest */
    private $intakeApiRequest;

    /** @var DataFromAgent */
    private $result;

    /** @var Logger */
    private $logger;

    public static function deserialize(IntakeApiRequest $intakeApiRequest): DataFromAgent
    {
        return (new self($intakeApiRequest))->deserializeImpl();
    }

    private function __construct(IntakeApiRequest $intakeApiRequest)
    {
        $this->intakeApiRequest = $intakeApiRequest;
        $this->result = new DataFromAgent();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function deserializeImpl(): DataFromAgent
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Processing intake API request');

        $isFirstLine = true;
        $encounteredEmptyLine = false;
        foreach (self::iterateLines($this->intakeApiRequest->body) as $bodyLine) {
            if (TextUtil::isEmptyString($bodyLine)) {
                $encounteredEmptyLine = true;
                continue;
            }
            // empty line can only be the last one
            TestCase::assertFalse($encounteredEmptyLine);

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Processing a line from intake API request', ['bodyLine' => $bodyLine]);

            $lineDecodedJson = JsonUtil::decode($bodyLine, /* asAssocArray */ true);
            TestCase::assertIsArray($lineDecodedJson);
            TestCase::assertCount(
                1,
                $lineDecodedJson,
                'Each decoded line should have exactly one top level key.' . " bodyLine: `$bodyLine'"
            );
            $linePayloadType = array_key_first($lineDecodedJson);
            $linePayloadDecodedJson = $lineDecodedJson[$linePayloadType];

            // metadata is the first line and only the first line
            TestCase::assertSame($isFirstLine, 'metadata' === $linePayloadType);

            $linePayloadEncodedJson = self::decodedJsonToString($linePayloadDecodedJson);

            switch ($linePayloadType) {
                case 'error':
                    $this->addError($linePayloadEncodedJson);
                    break;
                case 'metadata':
                    $this->addMetadata($linePayloadEncodedJson);
                    break;
                case 'metricset':
                    $this->addMetricSet($linePayloadEncodedJson);
                    break;
                case 'transaction':
                    $this->addTransaction($linePayloadEncodedJson);
                    break;
                case 'span':
                    $this->addSpan($linePayloadEncodedJson);
                    break;
                default:
                    TestCase::fail('Unexpected event kind `' . $linePayloadType . '\'.' . " bodyLine: `$bodyLine'");
            }
            $isFirstLine = false;
        }

        TestCase::assertCount(1, $this->result->metadatas);
        return $this->result;
    }

    private function addMetadata(string $encodedJson): void
    {
        $newMetadata  = $this->validateAndDeserializeMetadata($encodedJson);
        $this->result->metadatas[] = $newMetadata;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Added metadata', ['newMetadata' => $newMetadata]);
    }

    private function addTransaction(string $encodedJson): void
    {
        $newTransaction = $this->validateAndDeserializeTransaction($encodedJson);
        TestCase::assertNull($this->result->executionSegmentByIdOrNull($newTransaction->id));
        $this->result->idToTransaction[$newTransaction->id] = $newTransaction;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Added transaction', ['newTransaction' => $newTransaction]);
    }

    private function addSpan(string $encodedJson): void
    {
        $newSpan = $this->validateAndDeserializeSpan($encodedJson);
        TestCase::assertNull($this->result->executionSegmentByIdOrNull($newSpan->id));
        $this->result->idToSpan[$newSpan->id] = $newSpan;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Added span', ['newSpan' => $newSpan]);
    }

    private function addError(string $encodedJson): void
    {
        $newError = $this->validateAndDeserializeError($encodedJson);
        ArrayUtilForTests::addUnique($newError->id, $newError, /* ref */ $this->result->idToError);
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Added error', ['newError' => $newError]);
    }

    private function addMetricSet(string $encodedJson): void
    {
        $newMetricSet = $this->validateAndDeserializeMetricSet($encodedJson);
        $this->result->metricSets[] = $newMetricSet;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Added metric set', ['newMetricSet' => $newMetricSet]);
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
     * @param array<string, mixed> $decodedJson
     *
     * @return string
     */
    private static function decodedJsonToString(array $decodedJson): string
    {
        return JsonUtil::encode($decodedJson, /* prettyPrint: */ true);
    }
}
