<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

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
     * @return iterable<array<string>>
     */
    public function snakeToCamelCaseTestDataProvider(): iterable
    {
        yield ['', ''];
        yield ['a', 'a'];
        yield ['1', '1'];
        yield ['a_b', 'aB'];
        yield ['a1_b', 'a1B'];
        yield ['span_count', 'spanCount'];
        yield ['_span_count', 'spanCount'];
        yield ['__span__count', 'spanCount'];
        yield ['_', ''];
        yield ['__', ''];
        yield ['_x_', 'x'];
        yield ['x_y', 'xY'];
        yield ['x_1y', 'x1y'];
    }

    /**
     * @dataProvider camelToSnakeCaseTestDataProvider
     *
     * @param string $inputCamelCase
     * @param string $inputSnakeCase
     */
    public function testCamelToSnakeCase(string $inputCamelCase, string $inputSnakeCase): void
    {
        $this->assertSame($inputSnakeCase, TextUtil::camelToSnakeCase($inputCamelCase));
    }

    /**
     * @dataProvider snakeToCamelCaseTestDataProvider
     *
     * @param string $inputCamelCase
     * @param string $inputSnakeCase
     */
    public function testSnakeToCamelCase(string $inputSnakeCase, string $inputCamelCase): void
    {
        $this->assertSame($inputCamelCase, TextUtil::snakeToCamelCase($inputSnakeCase));
    }

    public function testIsSuffixOf(): void
    {
        self::assertTrue(TextUtil::isSuffixOf('', ''));
        self::assertTrue(TextUtil::isSuffixOf('', '', /* isCaseSensitive */ false));
        self::assertTrue(!TextUtil::isSuffixOf('a', ''));
        self::assertTrue(!TextUtil::isSuffixOf('a', '', /* isCaseSensitive */ false));

        self::assertTrue(TextUtil::isSuffixOf('C', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('c', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('c', 'ABC', /* isCaseSensitive */ false));

        self::assertTrue(TextUtil::isSuffixOf('BC', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('Bc', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('Bc', 'ABC', /* isCaseSensitive */ false));

        self::assertTrue(TextUtil::isSuffixOf('ABC', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('aBc', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('aBc', 'ABC', /* isCaseSensitive */ false));
    }
}
