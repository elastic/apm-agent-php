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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\UrlUtil;
use PHPUnit\Framework\TestCase;

class UrlUtilTest extends TestCase
{
    /**
     * @return array<array<string|int|null>>
     */
    public function dataProviderForSplitHostPort(): array
    {
        return [
            ['my_host_1', 'my_host_1', null],
            ['my-host-2:2', 'my-host-2', 2],
            ['my-host-3:', 'my-host-3', null],
            [':3', null, 3],
            ['4-my-host:65535', '4-my-host', 65535],
            ['my-host-5:abc', 'my-host-5', null],
            [' my-host-6 : 123 ', 'my-host-6', 123],
            [' my-host-7  : abc ', 'my-host-7', null],
            ['6.77.89.102', '6.77.89.102', null],
            ['255.254.253.252', '255.254.253.252', null],
            ['255.254.253.252:7654', '255.254.253.252', 7654],
            ['255.254.253.252:', '255.254.253.252', null],
            ['::1', '::1', null],
            ['[::1]:88', '::1', 88],
            ['[ ::1 ] : 88', '::1', 88],
            ['[::1]:', '::1', null],
            [' [ ::1 ] : ', '::1', null],
            ['[::1]', '::1', null],
            [' [ ::1 ] ', '::1', null],
            [' [ ::1 ] ', '::1', null],
            ['fe80::dcdf:ebd9:b60a:b3fb', 'fe80::dcdf:ebd9:b60a:b3fb', null],
            ['[fe80::dcdf:ebd9:b60a:b3fb]:9999', 'fe80::dcdf:ebd9:b60a:b3fb', 9999],
            ['[fe80::dcdf:ebd9:b60a:b3fb]', 'fe80::dcdf:ebd9:b60a:b3fb', null],
            ['[fe80::dcdf:ebd9:b60a:b3fb]:', 'fe80::dcdf:ebd9:b60a:b3fb', null],
            ['fe80::dcdf:ebd9:b60a:b3fb%3', 'fe80::dcdf:ebd9:b60a:b3fb%3', null],
            ['[fe80::dcdf:ebd9:b60a:b3fb%3]:9999', 'fe80::dcdf:ebd9:b60a:b3fb%3', 9999],
            ['[fe80::dcdf:ebd9:b60a:b3fb%3]:', 'fe80::dcdf:ebd9:b60a:b3fb%3', null],
            ['[fe80::dcdf:ebd9:b60a:b3fb%3]', 'fe80::dcdf:ebd9:b60a:b3fb%3', null],
        ];
    }

    /**
     * @dataProvider dataProviderForSplitHostPort
     *
     * @param string      $inputHostPort
     * @param string|null $expectedHost
     * @param int|null    $expectedPort
     */
    public function testSplitHostPort(string $inputHostPort, ?string $expectedHost, ?int $expectedPort): void
    {
        $actualHost = null;
        $actualPort = null;
        $this->assertTrue(UrlUtil::splitHostPort($inputHostPort, /* ref */ $actualHost, /* ref */ $actualPort));
        $this->assertSame($expectedHost, $actualHost);
        $this->assertSame($expectedPort, $actualPort);
    }
}
