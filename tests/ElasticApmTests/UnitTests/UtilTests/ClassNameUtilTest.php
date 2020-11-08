<?php

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
