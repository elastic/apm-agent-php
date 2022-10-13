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

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Impl\ServiceAgentData;
use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Impl\SystemData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\AssertValidTrait;
use ElasticApmTests\Util\MetadataValidator;

final class MetadataDeserializer
{
    use StaticClassTrait;
    use AssertValidTrait;

    /**
     * @param mixed $value
     *
     * @return Metadata
     */
    public static function deserialize($value): Metadata
    {
        $result = new Metadata();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'process':
                        $result->process = self::deserializeProcessData($value);
                        return true;

                    case 'service':
                        $result->service = self::deserializeServiceData($value);
                        return true;

                    case 'system':
                        $result->system = self::deserializeSystemData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetadataValidator::assertValid($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return ProcessData
     */
    private static function deserializeProcessData($value): ProcessData
    {
        $result = new ProcessData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'pid':
                        $result->pid = MetadataValidator::validateProcessId($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetadataValidator::validateProcessData($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return ServiceData
     */
    private static function deserializeServiceData($value): ServiceData
    {
        $result = new ServiceData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'agent':
                        $result->agent = self::deserializeServiceAgentData($value);
                        return true;
                    case 'environment':
                        $result->environment = self::assertValidKeywordString($value);
                        return true;
                    case 'framework':
                        $result->framework = self::deserializeNameVersionData($value);
                        return true;
                    case 'language':
                        $result->language = self::deserializeNameVersionData($value);
                        return true;
                    case 'name':
                        $result->name = self::assertValidKeywordString($value);
                        return true;
                    case 'node':
                        self::deserializeServiceNodeSubObject($value, $result);
                        return true;
                    case 'runtime':
                        $result->runtime = self::deserializeNameVersionData($value);
                        return true;
                    case 'version':
                        $result->version = self::assertValidKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetadataValidator::validateServiceDataEx($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return ServiceAgentData
     */
    private static function deserializeServiceAgentData($value): ServiceAgentData
    {
        $result = new ServiceAgentData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (self::deserializeNameVersionDataKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'ephemeral_id':
                        $result->ephemeralId = self::assertValidKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetadataValidator::validateServiceAgentDataEx($result);
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function deserializeServiceNodeSubObject($value, ServiceData $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'configured_name':
                        $result->nodeConfiguredName = self::assertValidKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }

    /**
     * @param mixed $value
     *
     * @return SystemData
     */
    private static function deserializeSystemData($value): SystemData
    {
        $result = new SystemData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'hostname':
                        $result->hostname = self::assertValidKeywordString($value);
                        return true;
                    case 'detected_hostname':
                        $result->detectedHostname = self::assertValidKeywordString($value);
                        return true;
                    case 'configured_hostname':
                        $result->configuredHostname = self::assertValidKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetadataValidator::validateSystemDataEx($result);
        return $result;
    }

    /**
     * @param mixed  $value
     *
     * @return NameVersionData
     */
    private static function deserializeNameVersionData($value): NameVersionData
    {
        $result = new NameVersionData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                return self::deserializeNameVersionDataKeyValue($key, $value, $result);
            }
        );
        MetadataValidator::validateNullableNameVersionData($result);
        return $result;
    }

    /**
     * @param mixed           $key
     * @param mixed           $value
     * @param NameVersionData $result
     *
     * @return bool
     */
    private static function deserializeNameVersionDataKeyValue($key, $value, NameVersionData $result): bool
    {
        switch ($key) {
            case 'name':
                $result->name = self::assertValidKeywordString($value);
                return true;
            case 'version':
                $result->version = self::assertValidKeywordString($value);
                return true;
            default:
                return false;
        }
    }
}
