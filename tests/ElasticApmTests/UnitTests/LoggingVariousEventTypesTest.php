<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\UnitTests\LogTests\LoggingVariousTypesTest;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class LoggingVariousEventTypesTest extends UnitTestCaseBase
{
    public function testTransaction(): void
    {
        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type', /* timestamp */ 12345654321);
        $loggedTx = LoggingVariousTypesTest::logValueAndDecodeToJson($tx);
        self::assertMapArrayIsSubsetOf(
            [
                'name'      => 'test_TX_name',
                'type'      => 'test_TX_type',
                'timestamp' => 12345654321,
            ],
            $loggedTx['data']
        );
    }
}
