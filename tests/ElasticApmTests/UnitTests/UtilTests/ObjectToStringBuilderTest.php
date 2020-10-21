<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\UtilTests;

use Elastic\Apm\Impl\NoopTransaction;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\TracerDependencies;
use Elastic\Apm\Impl\Transaction;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogSink;
use PHPUnit\Framework\TestCase;

class ObjectToStringBuilderTest extends TestCase
{
    private static function verifyClassToTestObjectToStringBuilder(string $str): void
    {
        self::assertStringContainsString('intField: 123', $str);
        self::assertStringContainsString('stringField: Abc', $str);
        self::assertStringContainsString('nullableStringField: <null>', $str);
    }

    public function testClassToTestObjectToStringBuilder(): void
    {
        $str = strval(new ClassToTestObjectToStringBuilder());
        self::assertStringStartsWith(DbgUtil::fqToShortClassName(ClassToTestObjectToStringBuilder::class), $str);
        self::verifyClassToTestObjectToStringBuilder($str);
    }

    public function testDerivedClassToTestObjectToStringBuilder(): void
    {
        $str = strval(new DerivedClassToTestObjectToStringBuilder());
        self::assertStringStartsWith(DbgUtil::fqToShortClassName(DerivedClassToTestObjectToStringBuilder::class), $str);
        self::verifyClassToTestObjectToStringBuilder($str);
        self::assertStringContainsString('derivedFloatField: 1.5', $str);
    }

    public function testClassToTestObjectToStringBuilderWithExcludedProperty(): void
    {
        $str = strval(new ClassToTestObjectToStringBuilderWithExcludedProperty());
        self::assertStringStartsWith(
            DbgUtil::fqToShortClassName(ClassToTestObjectToStringBuilderWithExcludedProperty::class),
            $str
        );
        self::verifyClassToTestObjectToStringBuilder($str);
        self::assertStringNotContainsString('excludedProperty', $str);
        self::assertStringContainsString('notExcludedProperty: 67', $str);
    }

    public function testNoopTransaction(): void
    {
        $str = strval(NoopTransaction::singletonInstance());
        self::assertSame('NoopTransaction', $str);
    }

    public function testTransaction(): void
    {
        $deps = new TracerDependencies();
        $deps->logSink = new TestLogSink(__CLASS__ . '::' . __METHOD__);
        $tx = new Transaction(
            new Tracer($deps),
            'test_TX_name',
            'test_TX_type',
            12345654321 /* timestamp */
        );
        $str = strval($tx);
        self::assertStringContainsString('name: test_TX_name', $str);
        self::assertStringContainsString('type: test_TX_type', $str);
        self::assertStringContainsString('timestamp: 12345654321', $str);
    }
}
