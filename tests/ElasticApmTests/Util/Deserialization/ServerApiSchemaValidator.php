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
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\TestsRootDir;
use ElasticApmTests\Util\ValidationUtil;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

final class ServerApiSchemaValidator
{
    private const EARLIEST_SUPPORTED_SPEC_DIR = 'earliest_supported/docs/spec';
    private const LATEST_USED_SPEC_DIR = 'latest_used/docs/spec';

    /** @var array<string, null> */
    private static $additionalPropertiesCandidateNodesKeys;

    /** @var array<string> */
    private $tempFilePaths = [];

    private static function pathToSpecsRootDir(): string
    {
        return TestsRootDir::$fullPath . '/APM_Server_intake_API_schema';
    }

    private static function isAdditionalPropertiesCandidate(string $key): bool
    {
        if (!isset(self::$additionalPropertiesCandidateNodesKeys)) {
            self::$additionalPropertiesCandidateNodesKeys = ['properties' => null, 'patternProperties' => null];
        }

        return array_key_exists($key, self::$additionalPropertiesCandidateNodesKeys);
    }

    /**
     * @param string $relativePath
     *
     * @return Closure(bool): string
     */
    public static function buildPathToSchemaSupplier(string $relativePath): Closure
    {
        return function (bool $isEarliestVariant) use ($relativePath) {
            return ($isEarliestVariant ? self::EARLIEST_SUPPORTED_SPEC_DIR : self::LATEST_USED_SPEC_DIR)
                   . '/' . $relativePath;
        };
    }

    public static function validateMetadata(string $serializedData): void
    {
        self::validateEventData($serializedData, self::buildPathToSchemaSupplier('metadata.json'));
    }

    public static function validateTransactionData(string $serializedData): void
    {
        self::validateEventData($serializedData, self::buildPathToSchemaSupplier('transactions/transaction.json'));
    }

    public static function validateSpanData(string $serializedData): void
    {
        self::validateEventData($serializedData, self::buildPathToSchemaSupplier('spans/span.json'));
    }

    public static function validateErrorData(string $serializedData): void
    {
        self::validateEventData($serializedData, self::buildPathToSchemaSupplier('errors/error.json'));
    }

    private static function validateEventData(string $serializedData, Closure $pathToSchemaSupplier): void
    {
        foreach ([true, false] as $isEarliestVariant) {
            $allowAdditionalPropertiesVariants = [true];
            if (!$isEarliestVariant) {
                $allowAdditionalPropertiesVariants[] = false;
            }
            foreach ($allowAdditionalPropertiesVariants as $allowAdditionalProperties) {
                (new self())->validateEventDataAgainstSchemaVariant(
                    $serializedData,
                    $pathToSchemaSupplier($isEarliestVariant),
                    $allowAdditionalProperties
                );
            }
        }
    }

    private function validateEventDataAgainstSchemaVariant(
        string $serializedData,
        string $relativePathToSchema,
        bool $allowAdditionalProperties
    ): void {
        try {
            $this->validateEventDataAgainstSchemaVariantImpl(
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

    private function validateEventDataAgainstSchemaVariantImpl(
        string $serializedData,
        string $relativePathToSchema,
        bool $allowAdditionalProperties
    ): void {
        $validator = new Validator();
        $deserializedRawData = JsonUtil::decode($serializedData, /* asAssocArray */ false);
        $validator->validate(
            $deserializedRawData,
            (object)($this->loadSchema(
                self::normalizePath(self::pathToSpecsRootDir() . '/' . $relativePathToSchema),
                $allowAdditionalProperties
            )),
            Constraint::CHECK_MODE_VALIDATE_SCHEMA
        );
        if (!$validator->isValid()) {
            throw self::buildException($relativePathToSchema, $validator, $serializedData);
        }
    }

    private static function normalizePath(string $absolutePath): string
    {
        $result = realpath($absolutePath);
        if ($result === false) {
            throw ValidationUtil::buildException("realpath failed. absolutePath: `$absolutePath'");
        }
        return $result;
    }

    /**
     * @param string $absolutePath
     * @param bool   $allowAdditionalProperties
     *
     * @return array<string, mixed>
     */
    private function loadSchema(string $absolutePath, bool $allowAdditionalProperties): array
    {
        if ($allowAdditionalProperties) {
            return ['$ref' => self::convertPathToFileUrl($absolutePath)];
        }

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
        if ($numberOfBytesWritten === false) {
            throw ValidationUtil::buildException("Failed to write to temp file `$pathToTempFile'");
        }
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
        if ($fileContents === false) {
            throw ValidationUtil::buildException("Failed to load schema from `$absolutePath'");
        }
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
        $schemaFromRef = self::loadSchemaAndResolveRefs(self::normalizePath(dirname($absolutePath) . '/' . $refValue));
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

        if (
            array_key_exists('allOf', $decodedSchemaNode)
            && self::atLeastOneChildFromRef($decodedSchemaNode['allOf'])
        ) {
            self::doMergeAllOfFromRef(/* ref */ $decodedSchemaNode);
            unset($decodedSchemaNode['allOf']);
        }
    }

    /**
     * @param array<string, mixed> $decodedSchemaNode
     */
    private static function doMergeAllOfFromRef(array &$decodedSchemaNode): void
    {
        foreach ($decodedSchemaNode['allOf'] as $childNode) {
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

                $dstArray = &$decodedSchemaNode[$key];
                foreach ($value as $subKey => $subValue) {
                    if (array_key_exists($key, $dstArray)) {
                        throw ValidationUtil::buildException(
                            'Failed to merge because key already exists.'
                            . "subKey: `$subKey'" . "; key: `$key'" . "; subValue: `$subValue'"
                        );
                    }
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
        foreach ($allOfArray as $key => $value) {
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
                    'errors' => $allErrorsToLoggable(),
                    'serializedData' => $serializedData,
                ]
            )
        );
    }
}
