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

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Parser
{
    /** @var Logger */
    private $logger;

    /**
     * Parser constructor.
     *
     * @param LoggerFactory $loggerFactory
     */
    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(LogCategory::CONFIGURATION, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @param string                       $rawValue
     * @param OptionParser<mixed> $optionParser
     *
     * @return mixed
     *
     * @template       T
     * @phpstan-param  OptionParser<T> $optionParser
     * @phpstan-return T
     */
    public static function parseOptionRawValue(string $rawValue, OptionParser $optionParser)
    {
        return $optionParser->parse(trim($rawValue));
    }

    /**
     * @param array<string, OptionMetadata<mixed>> $optNameToMeta
     * @param RawSnapshotInterface                          $rawSnapshot
     *
     * @return array<string, mixed> Option name to parsed value
     */
    public function parse(array $optNameToMeta, RawSnapshotInterface $rawSnapshot): array
    {
        $optNameToParsedValue = [];
        /** @var OptionMetadata<mixed> $optMeta */
        foreach ($optNameToMeta as $optName => $optMeta) {
            $rawValue = $rawSnapshot->valueFor($optName);
            if ($rawValue === null) {
                $parsedValue = $optMeta->defaultValue();

                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    "Input raw config snapshot doesn't have a value for the option - using default value",
                    ['Option name' => $optName, 'Option default value' => $optMeta->defaultValue()]
                );
            } else {
                try {
                    $parsedValue = self::parseOptionRawValue($rawValue, $optMeta->parser());

                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Input raw config snapshot has a value - using parsed value',
                        ['Option name' => $optName, 'Raw value' => $rawValue, 'Parsed value' => $parsedValue]
                    );
                } catch (ParseException $ex) {
                    $parsedValue = $optMeta->defaultValue();

                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        "Input raw config snapshot has a value but it's invalid - using default value",
                        [
                            'Option name'          => $optName,
                            'Option default value' => $optMeta->defaultValue(),
                            'Exception'            => $ex,
                        ]
                    );
                }
            }
            $optNameToParsedValue[$optName] = $parsedValue;
        }

        return $optNameToParsedValue;
    }
}
