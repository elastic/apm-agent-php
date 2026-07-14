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

use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<float>
 */
final class SizeOptionParser extends OptionParser
{
    /** @var ?float */
    private $minValidValueInBytes;

    /** @var ?float */
    private $maxValidValueInBytes;

    /** @var int */
    private $defaultUnits;

    public function __construct(
        ?float $minValidValueInBytes,
        ?float $maxValidValueInBytes,
        int $defaultUnits
    ) {
        $this->minValidValueInBytes = $minValidValueInBytes;
        $this->maxValidValueInBytes = $maxValidValueInBytes;
        $this->defaultUnits = $defaultUnits;
    }

    /** @inheritDoc */
    public function parse(string $rawValue): float
    {
        $partWithoutSuffix = '';
        $units = $this->defaultUnits;
        self::splitToValueAndUnits($rawValue, /* ref */ $partWithoutSuffix, /* ref */ $units);

        $auxIntOptionParser = new IntOptionParser(null, null);
        $parsedValueInBytes = self::convertToBytes($auxIntOptionParser->parse($partWithoutSuffix), $units);

        if (
            (($this->minValidValueInBytes !== null) && ($parsedValueInBytes < $this->minValidValueInBytes))
            || (($this->maxValidValueInBytes !== null) && ($parsedValueInBytes > $this->maxValidValueInBytes))
        ) {
            throw new ParseException(
                'Value is not in range between the valid minimum and maximum values.'
                . ' Raw option value: `' . $rawValue . "'."
                . ' Parsed option value (in bytes): ' . $parsedValueInBytes . '.'
                . ' The valid minimum value (in bytes): ' . $this->minValidValueInBytes . '.'
                . ' The valid maximum value (in bytes): ' . $this->maxValidValueInBytes . '.'
            );
        }

        return $parsedValueInBytes;
    }

    public function defaultUnits(): int
    {
        return $this->defaultUnits;
    }

    public function minValidValueInBytes(): ?float
    {
        return $this->minValidValueInBytes;
    }

    public function maxValidValueInBytes(): ?float
    {
        return $this->maxValidValueInBytes;
    }

    private static function splitToValueAndUnits(string $rawValue, string &$partWithoutSuffix, int &$units): void
    {
        foreach (SizeUnits::$suffixAndIdPairs as $suffixAndIdPair) {
            $suffix = $suffixAndIdPair[0];
            if (TextUtil::isSuffixOf($suffix, $rawValue, /* isCaseSensitive */ false)) {
                $partWithoutSuffix = trim(substr($rawValue, 0, -strlen($suffix)));
                $units = $suffixAndIdPair[1];
                return;
            }
        }

        $partWithoutSuffix = $rawValue;
    }

    public static function convertToBytes(int $srcValue, int $srcValueUnits): float
    {
        switch ($srcValueUnits) {
            case SizeUnits::BYTES:
                return floatval($srcValue);

            case SizeUnits::KILOBYTES:
                return floatval($srcValue) * 1024;

            case SizeUnits::MEGABYTES:
                return floatval($srcValue) * 1024 * 1024;

            case SizeUnits::GIGABYTES:
                return floatval($srcValue) * 1024 * 1024 * 1024;

            default:
                throw new ParseException(
                    ExceptionUtil::buildMessage(
                        'Not a valid size units ID',
                        /* context */
                        [
                            'srcValueUnits' => $srcValueUnits,
                            'srcValue'      => $srcValue,
                            'valid size units' => SizeUnits::$suffixAndIdPairs,
                        ]
                    )
                );
        }
    }
}
