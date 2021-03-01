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

use Elastic\Apm\Impl\Util\ClassNameUtil;
use PHPUnit\Framework\TestCase;

class ClassNameUtilTest extends TestCase
{
    /**
     * @return array<array<string>>
     */
    public function dataProviderForSplitFqClassName(): array
    {
        return [
            ['My\\Name\\Space\\MyClass', 'My\\Name\\Space', 'MyClass'],
            ['\\My\\Name\\Space\\MyClass', 'My\\Name\\Space', 'MyClass'],
            ['\\MyNameSpace\\MyClass', 'MyNameSpace', 'MyClass'],
            ['MyNameSpace\\MyClass', 'MyNameSpace', 'MyClass'],
            ['\\MyClass', '', 'MyClass'],
            ['MyClass', '', 'MyClass'],
            ['MyNameSpace\\', 'MyNameSpace', ''],
            ['\\MyNameSpace\\', 'MyNameSpace', ''],
            ['', '', ''],
            ['\\', '', ''],
            ['a\\', 'a', ''],
            ['\\a\\', 'a', ''],
            ['\\b', '', 'b'],
            ['\\\\', '', ''],
            ['\\\\\\', '\\', ''],
        ];
    }

    /**
     * @dataProvider dataProviderForSplitFqClassName
     *
     * @param string $fqName
     * @param string $expectedNamespace
     * @param string $expectedShortName
     */
    public function testSplitFqClassName(string $fqName, string $expectedNamespace, string $expectedShortName): void
    {
        $actualNamespace = '';
        $actualShortName = '';
        ClassNameUtil::splitFqClassName($fqName, /* ref */ $actualNamespace, /* ref */ $actualShortName);
        self::assertSame($expectedNamespace, $actualNamespace);
        self::assertSame($expectedShortName, $actualShortName);
    }
}
