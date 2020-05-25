<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

final class TestNetUtil
{
    use StaticClassTrait;

    // public static function isPortFreeToListen(int $port): bool
    // {
    //     // $socket = socket_create(/* domain */ AF_INET, /* type */ SOCK_STREAM, getprotobyname('tcp'));
    //     // try {
    //     //
    //     //     return true;
    //     //
    //     // } finally {
    //     //     socket_close($socket);
    //     // }
    //     return false;
    // }
}
