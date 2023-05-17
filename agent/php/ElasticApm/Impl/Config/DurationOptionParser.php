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
final class DurationOptionParser extends OptionParser
{
    /** @var float|null */
    private $minValidValueInMilliseconds;

    /** @var float|null */
    private $maxValidValueInMilliseconds;

    /** @var int */
    private $defaultUnits;

    public function __construct(
        ?float $minValidValueInMilliseconds,
        ?float $maxValidValueInMilliseconds,
        int $defaultUnits
    ) {
        $this->minValidValueInMilliseconds = $minValidValueInMilliseconds;
        $this->maxValidValueInMilliseconds = $maxValidValueInMilliseconds;
        $this->defaultUnits = $defaultUnits;
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return float
     */
    public function parse(string $rawValue)
    {
        $partWithoutSuffix = '';
        $units = $this->defaultUnits;
        self::splitToValueAndUnits($rawValue, /* ref */ $partWithoutSuffix, /* ref */ $units);

        $auxFloatOptionParser = new FloatOptionParser(null /* minValidValue */, null /* maxValidValue */);
        $parsedValueInMilliseconds
            = self::convertToMilliseconds($auxFloatOptionParser->parse($partWithoutSuffix), $units);

        if (
            (
                ($this->minValidValueInMilliseconds !== null)
                && ($parsedValueInMilliseconds < $this->minValidValueInMilliseconds)
            )
            || (
                ($this->maxValidValueInMilliseconds !== null)
                && ($parsedValueInMilliseconds > $this->maxValidValueInMilliseconds)
            )
        ) {
            throw new ParseException(
                'Value is not in range between the valid minimum and maximum values.'
                . ' Raw option value: `' . $rawValue . "'."
                . ' Parsed option value (in milliseconds): ' . $parsedValueInMilliseconds . '.'
                . ' The valid minimum value (in milliseconds): ' . $this->minValidValueInMilliseconds . '.'
                . ' The valid maximum value (in milliseconds): ' . $this->maxValidValueInMilliseconds . '.'
            );
        }

        return $parsedValueInMilliseconds;
    }

    public function defaultUnits(): int
    {
        return $this->defaultUnits;
    }

    public function minValidValueInMilliseconds(): ?float
    {
        return $this->minValidValueInMilliseconds;
    }

    public function maxValidValueInMilliseconds(): ?float
    {
        return $this->maxValidValueInMilliseconds;
    }

    private static function splitToValueAndUnits(string $rawValue, string &$partWithoutSuffix, int &$units): void
    {
        foreach (DurationUnits::$suffixAndIdPairs as $suffixAndIdPair) {
            $suffix = $suffixAndIdPair[0];
            if (TextUtil::isSuffixOf($suffix, $rawValue, /* isCaseSensitive */ false)) {
                $partWithoutSuffix = trim(substr($rawValue, 0, -strlen($suffix)));
                $units = $suffixAndIdPair[1];
                return;
            }
        }
        $partWithoutSuffix = $rawValue;
    }

    public static function convertToMilliseconds(float $srcValue, int $srcValueUnits): float
    {
        switch ($srcValueUnits) {
            case DurationUnits::MILLISECONDS:
                return $srcValue;

            case DurationUnits::SECONDS:
                return $srcValue * 1000;

            case DurationUnits::MINUTES:
                return $srcValue * 60 * 1000;

            default:
                throw new ParseException(
                    ExceptionUtil::buildMessage(
                        'Not a valid time duration units ID',
                        /* context */
                        [
                            'srcValueUnits' => $srcValueUnits,
                            'srcValue'      => $srcValue,
                            'valid time duration units' => DurationUnits::$suffixAndIdPairs,
                        ]
                    )
                );
        }
    }
}
