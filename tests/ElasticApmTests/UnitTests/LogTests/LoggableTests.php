<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\LogTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Log\LogToJsonUtil;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\Tests\Util\SerializationTestUtil;

class LoggableTests extends UnitTestCaseBase
{
    public function testLoggableToString(): void
    {
        $loggableObj = new class implements LoggableInterface {
            /** @var bool */
            private $isRecursive;

            public function __construct(bool $isRecursive = true)
            {
                $this->isRecursive = $isRecursive;
            }

            public function toLog(LogStreamInterface $logStream): void
            {
                $logStream->writeMap(
                    [
                        'A_null' => null,
                        'B_map'  => [
                            'B_A_map'         => [
                                'B_A_A_float' => 5432.1,
                                'B_A_B_self_or_null' => $this->isRecursive ? new self(/* $isRecursive */ false) : null
                            ],
                            'B_B_list_string' => ['val_B_B_0', 'val_B_B_1'],
                            'B_C_list_self_or_null' => [
                                $this->isRecursive ? new self(/* $isRecursive */ false) : null
                            ],
                        ],
                        'C_int'  => 123
                    ]
                );
            }
        };
        $jsonEncoded = LogToJsonUtil::toString($loggableObj);

        // Assert
        /** @var array<string, mixed> */
        $topDecodedJson = SerializationTestUtil::deserializeJson($jsonEncoded, /* $asAssocArray */ true);
        $assertDecodedJson = function (array $decodedJson, bool $isRecursive) use (&$assertDecodedJson) {
            self::assertIsArrayWithCount(3, $decodedJson);
            self::assertNull(self::assertAndGetValueByKey($decodedJson, 'A_null'));
            /** @var array<string, mixed> */
            $bMap = self::assertAndGetValueByKey($decodedJson, 'B_map');
            self::assertIsArrayWithCount(2, $bMap);
            /** @var array<string, mixed> */
            $baMap = self::assertAndGetValueByKey($bMap, 'B_A_map');
            self::assertSame(5432.1, self::assertAndGetValueByKey($baMap, 'B_A_A_float'));

            $babSelfOrNull = self::assertAndGetValueByKey($baMap, 'B_A_B_self_or_null');
            if ($isRecursive) {
                self::assertNotNull($babSelfOrNull);
                $assertDecodedJson($babSelfOrNull, /* $isRecursive */ false);
            } else {
                self::assertNull($babSelfOrNull);
            }

            /** @var array<string> */
            $bbList = self::assertAndGetValueByKey($bMap, 'B_B_list_string');
            self::assertIsArrayWithCount(2, $bbList);
            self::assertSame('val_B_B_0', self::assertAndGetValueByKey($bbList, 0));
            self::assertSame('val_B_B_1', self::assertAndGetValueByKey($bbList, 1));

            $cInt = self::assertAndGetValueByKey($decodedJson, 'C_int');
            self::assertSame(123, $cInt);
        };

        $assertDecodedJson($topDecodedJson, /* $isRecursive */ true);
    }
}
