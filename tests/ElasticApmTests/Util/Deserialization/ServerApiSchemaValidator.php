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

use Closure;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ExternalTestData;
use ElasticApmTests\Util\FileUtilForTests;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class ServerApiSchemaValidator
{
    private const ERROR_SCHEMA_INDEX = 0;
    private const METADATA_SCHEMA_INDEX = 1;
    private const SPAN_SCHEMA_INDEX = 2;
    private const TRANSACTION_SCHEMA_INDEX = 3;
    private const METRIC_SET_SCHEMA_INDEX = 4;

    private const EARLIEST_SUPPORTED_SPEC_DIR = 'earliest_supported/docs/spec';
    private const EARLIEST_SUPPORTED_SCHEMAS_REL_PATHS
        = [
            self::ERROR_SCHEMA_INDEX       => 'errors/error.json',
            self::METADATA_SCHEMA_INDEX    => 'metadata.json',
            self::SPAN_SCHEMA_INDEX        => 'spans/span.json',
            self::TRANSACTION_SCHEMA_INDEX => 'transactions/transaction.json',
            self::METRIC_SET_SCHEMA_INDEX  => 'metricsets/metricset.json',
        ];

    private const LATEST_USED_SPEC_DIR = 'latest_used';
    private const LATEST_USED_SCHEMAS_REL_PATHS
        = [
            self::ERROR_SCHEMA_INDEX       => 'error.json',
            self::METADATA_SCHEMA_INDEX    => 'metadata.json',
            self::SPAN_SCHEMA_INDEX        => 'span.json',
            self::TRANSACTION_SCHEMA_INDEX => 'transaction.json',
            self::METRIC_SET_SCHEMA_INDEX  => 'metricset.json',
        ];

    /** @var ?array<string, null> */
    private static $additionalPropertiesCandidateNodesKeys = null;

    /** @var array<string> */
    private $tempFilePaths = [];

    private static function isAdditionalPropertiesCandidate(string $key): bool
    {
        if (self::$additionalPropertiesCandidateNodesKeys === null) {
            self::$additionalPropertiesCandidateNodesKeys = ['properties' => null, 'patternProperties' => null];
        }

        return array_key_exists($key, self::$additionalPropertiesCandidateNodesKeys);
    }

    /**
     * @param int $schemaIndex
     *
     * @return Closure(bool): string
     */
    public static function buildPathToSchemaSupplier(int $schemaIndex): Closure
    {
        return function (bool $isEarliestVariant) use ($schemaIndex) {
            return $isEarliestVariant
                ? self::EARLIEST_SUPPORTED_SPEC_DIR . '/' . self::EARLIEST_SUPPORTED_SCHEMAS_REL_PATHS[$schemaIndex]
                : self::LATEST_USED_SPEC_DIR . '/' . self::LATEST_USED_SCHEMAS_REL_PATHS[$schemaIndex];
        };
    }

    public static function validateMetadata(string $serializedData): void
    {
        self::validateEvent($serializedData, self::buildPathToSchemaSupplier(self::METADATA_SCHEMA_INDEX));
    }

    public static function validateTransaction(string $serializedData): void
    {
        self::validateEvent($serializedData, self::buildPathToSchemaSupplier(self::TRANSACTION_SCHEMA_INDEX));
    }

    public static function validateSpan(string $serializedData): void
    {
        self::validateEvent($serializedData, self::buildPathToSchemaSupplier(self::SPAN_SCHEMA_INDEX));
    }

    public static function validateError(string $serializedData): void
    {
        self::validateEvent($serializedData, self::buildPathToSchemaSupplier(self::ERROR_SCHEMA_INDEX));
    }

    public static function validateMetricSet(string $serializedData): void
    {
        self::validateEvent($serializedData, self::buildPathToSchemaSupplier(self::METRIC_SET_SCHEMA_INDEX));
    }

    private static function validateEvent(string $serializedData, Closure $pathToSchemaSupplier): void
    {
        foreach ([true, false] as $isEarliestVariant) {
            $allowAdditionalPropertiesVariants = [true];
            if (!$isEarliestVariant) {
                $allowAdditionalPropertiesVariants[] = false;
            }
            foreach ($allowAdditionalPropertiesVariants as $allowAdditionalProperties) {
                (new self())->validateEventAgainstSchemaVariant(
                    $serializedData,
                    $pathToSchemaSupplier($isEarliestVariant),
                    $allowAdditionalProperties
                );
            }
        }
    }

    private function validateEventAgainstSchemaVariant(
        string $serializedData,
        string $relativePathToSchema,
        bool $allowAdditionalProperties
    ): void {
        try {
            $this->validateEventAgainstSchemaVariantImpl(
                $serializedData,
                $relativePathToSchema,
                $allowAdditionalProperties
            );
        } finally {
            foreach ($this->tempFilePaths as $tempFilePath) {
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
            }
        }
    }

    private function validateEventAgainstSchemaVariantImpl(
        string $serializedData,
        string $relativePathToSchema,
        bool $allowAdditionalProperties
    ): void {
        $validator = new Validator();
        $deserializedRawData = JsonUtil::decode($serializedData, /* asAssocArray */ false);
        $validator->validate(
            $deserializedRawData,
            (object)($this->loadSchema(
                ExternalTestData::fullPathForFileInApmServerIntakeApiSchemaDir($relativePathToSchema),
                $allowAdditionalProperties
            )),
            Constraint::CHECK_MODE_VALIDATE_SCHEMA
        );
        if (!$validator->isValid()) {
            throw self::buildException($relativePathToSchema, $validator, $serializedData);
        }
    }

    /**
     * @param string $absolutePath
     * @param bool   $allowAdditionalProperties
     *
     * @return array<string, mixed>
     */
    private function loadSchema(string $absolutePath, bool $allowAdditionalProperties): array
    {
        $decodedSchema = self::loadSchemaAndResolveRefs($absolutePath);
        self::processSchema(/* ref */ $decodedSchema, $allowAdditionalProperties);
        $pathToTempFileWithProcessedSchema = $this->writeProcessedSchemaToTempFile($decodedSchema);
        return ['$ref' => self::convertPathToFileUrl($pathToTempFileWithProcessedSchema)];
    }

    private static function convertPathToFileUrl(string $absolutePath): string
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return 'file://' . $absolutePath;
        }

        return 'file:///' . str_replace('\\', '/', $absolutePath);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return string - Absolute path to the temp file
     */
    private function writeProcessedSchemaToTempFile(array $schema): string
    {
        $pathToTempFile = tempnam(sys_get_temp_dir(), '');
        $pathToTempFile .= '_' . str_replace('\\', '_', __CLASS__) . '_temp_processed_schema.json';
        $this->tempFilePaths[] = $pathToTempFile;
        $numberOfBytesWritten = file_put_contents(
            $pathToTempFile,
            JsonUtil::encode($schema, /* prettyPrint: */ true),
            /* flags */ LOCK_EX
        );
        TestCase::assertNotFalse($numberOfBytesWritten, "Failed to write to temp file `$pathToTempFile'");
        return $pathToTempFile;
    }

    /**
     * @param string $absolutePath
     *
     * @return array<string, mixed>
     */
    private static function loadSchemaAndResolveRefs(string $absolutePath): array
    {
        $fileContents = file_get_contents($absolutePath);
        TestCase::assertNotFalse($fileContents, "Failed to load schema from `$absolutePath'");
        $decodedSchema = JsonUtil::decode($fileContents, /* asAssocArray */ true);
        self::resolveRefs($absolutePath, /* ref */ $decodedSchema);
        return $decodedSchema;
    }

    /**
     * @param string               $absolutePath
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function resolveRefs(string $absolutePath, array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode as $key => $value) {
            if (is_array($value)) {
                self::resolveRefs($absolutePath, /* ref */ $decodedSchemaNode[$key]);
            }
        }

        if (!array_key_exists('$ref', $decodedSchemaNode)) {
            return;
        }

        /** @var string */
        $refValue = $decodedSchemaNode['$ref'];
        self::loadRefAndMerge($absolutePath, /* ref */ $decodedSchemaNode, $refValue);
    }

    /**
     * @param string               $absolutePath
     * @param array<string, mixed> $refParentNode
     * @param string               $refValue
     */
    private static function loadRefAndMerge(string $absolutePath, array &$refParentNode, string $refValue): void
    {
        $schemaFromRef = self::loadSchemaAndResolveRefs(
            FileUtilForTests::normalizePath(dirname($absolutePath) . '/' . $refValue)
        );
        foreach ($schemaFromRef as $key => $value) {
            if (!array_key_exists($key, $refParentNode)) {
                $refParentNode[$key] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $decodedSchema
     * @param bool                 $allowAdditionalProperties
     */
    private static function processSchema(array &$decodedSchema, bool $allowAdditionalProperties): void
    {
        self::mergeAllOfFromRef(/* ref */ $decodedSchema);
        if (!$allowAdditionalProperties) {
            self::disableAdditionalProperties(/* ref */ $decodedSchema);
        }

        self::removeRedundantKeysFromRef(/* ref */ $decodedSchema);

        self::adjustTimestampType(/* ref */ $decodedSchema);
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function mergeAllOfFromRef(array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode as $key => $value) {
            if (is_array($value)) {
                self::mergeAllOfFromRef(/* ref */ $decodedSchemaNode[$key]);
            }
        }

        if (array_key_exists('allOf', $decodedSchemaNode)) {
            /** @var array<mixed> */
            $decodedSchemaNodeAllOf = $decodedSchemaNode['allOf'];
            if (self::atLeastOneChildFromRef($decodedSchemaNodeAllOf)) {
                self::doMergeAllOfFromRef(/* ref */ $decodedSchemaNode);
                unset($decodedSchemaNode['allOf']);
            }
        }
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function doMergeAllOfFromRef(array &$decodedSchemaNode): void
    {
        /** @var array<mixed> */
        $decodedSchemaNodeAllOf = $decodedSchemaNode['allOf'];
        foreach ($decodedSchemaNodeAllOf as $childNode) {
            /** @var array<mixed, mixed> $childNode */
            /** @var string $key */
            foreach ($childNode as $key => $value) {
                if ($key === '$ref' || $key === '$id' || $key === 'title') {
                    continue;
                }

                if (!array_key_exists($key, $decodedSchemaNode)) {
                    $decodedSchemaNode[$key] = $value;
                    continue;
                }

                if (!is_array($decodedSchemaNode[$key])) {
                    continue;
                }

                /** @var array<mixed, mixed> */
                $dstArray = &$decodedSchemaNode[$key];
                /** @var array<mixed, mixed> $value */
                foreach ($value as $subKey => $subValue) {
                    TestCase::assertArrayNotHasKey(
                        $key,
                        $dstArray,
                        'Failed to merge because key already exists.'
                        . LoggableToString::convert(['subKey' => $subKey, 'key' => $key, 'subValue' => $subValue])
                    );
                    $dstArray[$subKey] = $subValue;
                }
            }
        }
    }

    /**
     * @param array<mixed> $allOfArray
     *
     * @return bool
     */
    private static function atLeastOneChildFromRef(array $allOfArray): bool
    {
        foreach ($allOfArray as $value) {
            /** @var array<mixed, mixed> $value */
            if (array_key_exists('$ref', $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function disableAdditionalProperties(array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode as $key => $value) {
            if (is_string($key) && self::isAdditionalPropertiesCandidate($key)) {
                $decodedSchemaNode['additionalProperties'] = false;
            }
            if ($key !== 'allOf' && $key !== 'anyOf' && is_array($value)) {
                self::disableAdditionalProperties(/* ref */ $decodedSchemaNode[$key]);
            }
        }
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function removeRedundantKeysFromRef(array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode as $key => $value) {
            if (is_array($value)) {
                self::removeRedundantKeysFromRef(/* ref */ $decodedSchemaNode[$key]);
            }
        }

        if (!array_key_exists('$ref', $decodedSchemaNode)) {
            return;
        }

        unset($decodedSchemaNode['$ref']);
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function adjustTimestampType(array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode as $key => $value) {
            if (is_array($value)) {
                self::adjustTimestampType(/* ref */ $decodedSchemaNode[$key]);
            }
        }

        if (!array_key_exists('timestamp', $decodedSchemaNode)) {
            return;
        }

        // from earliest_supported/docs/spec/timestamp_epoch.json
        //      "timestamp": {
        //          "type": ["integer", "null"]
        //      }

        // from earliest_supported/docs/spec/metricsets/metricset.json
        //      "timestamp": {
        //          "type": "integer"
        //      }

        $timestampVal = &$decodedSchemaNode['timestamp'];
        if (!is_array($timestampVal)) {
            return;
        }
        /** @var array<mixed, mixed> $timestampVal */
        $typeVal = &$timestampVal['type'];
        if (is_array($typeVal)) {
            if (count($typeVal) !== 2) {
                return;
            }
            /** @var array<mixed, mixed> $typeVal */
            if (
                !(in_array('null', $typeVal, /* $strict: */ true)
                  && in_array('integer', $typeVal, /* $strict: */ true))
            ) {
                return;
            }
            $typeVal = ['number' , 'null'];
        } else {
            if ($typeVal !== 'integer') {
                return;
            }
            $typeVal = 'number';
        }
    }

    private static function buildException(
        string $relativePathToSchema,
        Validator $validator,
        string $serializedData
    ): ServerApiSchemaValidationException {
        $errors = $validator->getErrors();

        /**
         * @param array<string, mixed> $error
         *
         * @return array<string, mixed>
         */
        $errorToLoggable = function (array $error): array {
            /**
             * @param string $key
             *
             * @return array<string, mixed>
             */
            $nameToValueIfNotNullOrEmpty = function (string $key) use ($error): array {
                return TextUtil::isNullOrEmptyString($error[$key]) ? [] : [$key => $error[$key]];
            };

            $result[] = $nameToValueIfNotNullOrEmpty('message');
            $result[] = $nameToValueIfNotNullOrEmpty('property');
            $result[] = $nameToValueIfNotNullOrEmpty('pointer');
            return $result;
        };

        /**
         * @return array<string, mixed>
         */
        $allErrorsToLoggable = function () use ($errors, $errorToLoggable): array {
            $result = [];
            foreach ($errors as $error) {
                $result[] = $errorToLoggable($error);
            }
            return $result;
        };

        return new ServerApiSchemaValidationException(
            ExceptionUtil::buildMessage(
                'Serialized data failed APM Server Intake API JSON schema validation',
                [
                    'relativePathToSchema' => $relativePathToSchema,
                    'errors'               => $allErrorsToLoggable(),
                    'serializedData'       => $serializedData,
                ]
            )
        );
    }
}
