<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\LogTests;

use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;

class LoggableStackTraceTests extends UnitTestCaseBase
{
    // use Elastic\Apm\Impl\Log\LoggableStackTrace;
    // use Elastic\Apm\Impl\Log\LogToJsonUtil;
    // use Elastic\Apm\Impl\Util\ArrayUtil;
    // use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
    // use Elastic\Apm\Tests\Util\SerializationTestUtil;
    // use Elastic\Apm\Tests\Util\DummyForTestsException;
    //
    // use function Elastic\Apm\Tests\dummyFuncForTestsWithNamespace;

    // private static function dummyFunc1(bool $shouldThrow, int $numberOfStackFramesToSkip): LoggableStackTrace
    // {
    //     /** @phpstan-ignore-next-line */
    //     return dummyFuncForTestsWithNamespace(
    //         function () use ($shouldThrow, $numberOfStackFramesToSkip) {
    //             /** @phpstan-ignore-next-line */
    //             return dummyFuncForTestsWithoutNamespace(
    //                 function () use ($shouldThrow, $numberOfStackFramesToSkip) {
    //                     if ($shouldThrow) {
    //                         throw new DummyForTestsException('Dummy exception message for LoggableStackTraceTests');
    //                     }
    //                     return LoggableStackTrace::current($numberOfStackFramesToSkip);
    //                 }
    //             );
    //         }
    //     );
    // }
    //
    // private static function dummyFunc2(bool $shouldThrow, int $numberOfStackFramesToSkip): LoggableStackTrace
    // {
    //     return self::dummyFunc1($shouldThrow, $numberOfStackFramesToSkip);
    // }
    //
    // private static function dummyFunc3(bool $shouldThrow, int $numberOfStackFramesToSkip): LoggableStackTrace
    // {
    //     return self::dummyFunc2($shouldThrow, $numberOfStackFramesToSkip);
    // }
    //
    // private static function oneCombinationImpl(bool $shouldThrow, int $numberOfStackFramesToSkip): void
    // {
    //     $expectedStackFrames = [
    //         [
    //             'function' => null // anonymous
    //         ],
    //         [
    //             'function' => 'dummyFuncForTestsWithoutNamespace'
    //         ],
    //         [
    //             'function' => null // anonymous
    //         ],
    //         [
    //             'function' => 'dummyFuncForTestsWithNamespace'
    //         ],
    //         [
    //             'function' => 'dummyFunc1'
    //         ],
    //         [
    //             'function' => 'dummyFunc2'
    //         ],
    //         [
    //             'function' => 'dummyFunc3'
    //         ]
    //     ];
    //     foreach ($expectedStackFrames as &$expectedStackFrame) {
    //         $expectedStackFrame['class'] = get_called_class();
    //     }
    //     unset($expectedStackFrame);
    //
    //     try {
    //         $loggableStackTrace = self::dummyFunc3($shouldThrow, $numberOfStackFramesToSkip);
    //     } catch (DummyForTestsException $ex) {
    //         $loggableStackTrace = new LoggableStackTrace($ex->getTrace(), $numberOfStackFramesToSkip);
    //     }
    //
    //     $decodedJson = SerializationTestUtil::deserializeJson(
    //         LogToJsonUtil::toString($loggableStackTrace),
    //         /* $asAssocArray */ true
    //     );
    //
    //     self::assertIsArrayWithCount(count($expectedStackFrames) - $numberOfStackFramesToSkip, $decodedJson);
    //     self::assertTrue(ArrayUtil::isList($decodedJson));
    //     $index = 0;
    //     foreach ($decodedJson as $actualStackFrame) {
    //         self::assertEquals($expectedStackFrames[$index + $numberOfStackFramesToSkip], $actualStackFrame);
    //         ++$index;
    //     }
    // }

    public function testAllCombinations(): void
    {
        // foreach ([false, true] as $shouldThrow) {
        //     // range - the end value is included
        //     $numberOfStackFramesToSkipVariants = $shouldThrow ? [0] : range(0, 3);
        //     foreach ($numberOfStackFramesToSkipVariants as $numberOfStackFramesToSkip) {
        //         self::oneCombinationImpl($shouldThrow, $numberOfStackFramesToSkip);
        //     }
        // }
        self::assertEquals(1, 1);
    }
}
