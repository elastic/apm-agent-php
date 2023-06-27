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
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TextUtilForTests;
use stdClass;

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

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_UTIL, __NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);
    }

    public function deserializeImpl(): DataFromAgent
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Processing intake API request');

        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['this' => $this]);

        $isFirstLine = true;
        $encounteredEmptyLine = false;
        $dbgCtx->pushSubScope();
        foreach (TextUtilForTests::iterateLines($this->intakeApiRequest->body, /* keepEndOfLine */ false) as $bodyLine) {
            $dbgCtx->add(['bodyLine' => $bodyLine]);
            if (TextUtil::isEmptyString($bodyLine)) {
                // There should be only one empty line (at the end)
                TestCaseBase::assertFalse($encounteredEmptyLine);
                $encounteredEmptyLine = true;
                continue;
            }
            // empty line can only be the last one
            TestCaseBase::assertFalse($encounteredEmptyLine);

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Processing a line from intake API request', ['bodyLine' => $bodyLine]);

            $lineDecodedJson = JsonUtil::decode($bodyLine, /* asAssocArray */ false);
            TestCaseBase::assertInstanceOf(stdClass::class, $lineDecodedJson);
            $lineDecodedJsonAsArray = get_object_vars($lineDecodedJson);
            TestCaseBase::assertCount(1, $lineDecodedJsonAsArray, 'Each decoded line should have exactly one top level key');
            $linePayloadType = array_key_first($lineDecodedJsonAsArray);
            $linePayloadDecodedJson = $lineDecodedJsonAsArray[$linePayloadType];

            // metadata is the first line and only the first line
            TestCaseBase::assertSame($isFirstLine, 'metadata' === $linePayloadType);

            $linePayloadEncodedJson = JsonUtil::encode($linePayloadDecodedJson, /* prettyPrint: */ true);

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
                    TestCaseBase::fail('Unexpected event kind `' . $linePayloadType . '\'.' . " bodyLine: `$bodyLine'");
            }
            $isFirstLine = false;
        }
        $dbgCtx->popSubScope();

        TestCaseBase::assertCount(1, $this->result->metadatas);
        return $this->result;
    }

    private function addMetadata(string $encodedJson): void
    {
        $newMetadata  = $this->validateAndDeserializeMetadata($encodedJson);
        $this->result->metadatas[] = $newMetadata;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added metadata', ['newMetadata' => $newMetadata]);
    }

    private function addTransaction(string $encodedJson): void
    {
        $newTransaction = $this->validateAndDeserializeTransaction($encodedJson);
        TestCaseBase::assertNull($this->result->executionSegmentByIdOrNull($newTransaction->id));
        $this->result->idToTransaction[$newTransaction->id] = $newTransaction;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added transaction', ['newTransaction' => $newTransaction]);
    }

    private function addSpan(string $encodedJson): void
    {
        $newSpan = $this->validateAndDeserializeSpan($encodedJson);
        TestCaseBase::assertNull($this->result->executionSegmentByIdOrNull($newSpan->id));
        $this->result->idToSpan[$newSpan->id] = $newSpan;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added span', ['newSpan' => $newSpan]);
    }

    private function addError(string $encodedJson): void
    {
        $newError = $this->validateAndDeserializeError($encodedJson);
        ArrayUtilForTests::addUnique($newError->id, $newError, /* ref */ $this->result->idToError);
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added error', ['newError' => $newError]);
    }

    private function addMetricSet(string $encodedJson): void
    {
        $newMetricSet = $this->validateAndDeserializeMetricSet($encodedJson);
        $this->result->metricSets[] = $newMetricSet;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added metric set', ['newMetricSet' => $newMetricSet]);
    }
}
