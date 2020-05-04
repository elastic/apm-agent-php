<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UtilTests;

use Elastic\Apm\Impl\Util\TextUtil;
use PHPUnit\Framework\TestCase;

class TextUtilTest extends TestCase
{
    /**
     * @return array<array<string>>
     */
    public function camelToSnakeCaseTestDataProvider(): array
    {
        return [
            ['', ''],
            ['a', 'a'],
            ['B', 'b'],
            ['1', '1'],
            ['aB', 'a_b'],
            ['AB', 'a_b'],
            ['Ab', 'ab'],
            ['Ab1', 'ab1'],
            ['spanCount', 'span_count'],
        ];
    }

    /**
     * @dataProvider camelToSnakeCaseTestDataProvider
     */
    public function testCamelToSnakeCase(string $input, string $expectedResult): void
    {
        $this->assertSame($expectedResult, TextUtil::camelToSnakeCase($input));
    }
}
