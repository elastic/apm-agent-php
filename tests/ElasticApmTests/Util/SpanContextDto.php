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

namespace ElasticApmTests\Util;

use ElasticApmTests\Util\Deserialization\DeserializationUtil;

final class SpanContextDto extends ExecutionSegmentContextDto
{
    /** @var ?SpanContextDbDto */
    public $db = null;

    /** @var ?SpanContextDestinationDto */
    public $destination = null;

    /** @var ?SpanContextHttpDto */
    public $http = null;

    /** @var ?SpanContextServiceDto */
    public $service = null;

    /**
     * @param mixed $value
     *
     * @return self
     */
    public static function deserialize($value): self
    {
        $result = new self();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (parent::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'db':
                        $result->db = SpanContextDbDto::deserialize($value);
                        return true;
                    case 'destination':
                        $result->destination = SpanContextDestinationDto::deserialize($value);
                        return true;
                    case 'http':
                        $result->http = SpanContextHttpDto::deserialize($value);
                        return true;
                    case 'service':
                        $result->service = SpanContextServiceDto::deserialize($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    public function assertMatches(SpanContextExpectations $expectations): void
    {
        parent::assertMatchesExecutionSegment($expectations);

        SpanContextDbExpectations::assertNullableMatches($expectations->db, $this->db);
        SpanContextDestinationExpectations::assertNullableMatches($expectations->destination, $this->destination);
        SpanContextHttpExpectations::assertNullableMatches($expectations->http, $this->http);
        SpanContextServiceExpectations::assertNullableMatches($expectations->service, $this->service);
    }

    public function assertValid(): void
    {
        $this->assertMatches(new SpanContextExpectations());
    }
}
