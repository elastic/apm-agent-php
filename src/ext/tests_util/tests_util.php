<?php

declare(strict_types=1);

error_reporting(E_ALL | E_STRICT);

function elasticApmOnAssertFailure(string $condDesc, $expected, $actual, string $expr)
{
    if ( $expected === $actual ) return;

    $indent = "\t\t\t\t\t\t";
    echo "========================================\n";
    echo "====================\n";
    echo "===\n";
    echo "\n";
    echo "Expected and actual values for:\n";
    echo "\n$indent";
    echo "$expr\n";
    echo "\n";
    echo "are not $condDesc.\n";
    echo "\n";
    echo "Expected value:\n";
    echo "\n$indent";
    var_dump( $expected );
    echo "\n";
    echo "Actual value:\n";
    echo "\n$indent";
    var_dump( $actual );
    echo "\n";
    echo "===\n";
    echo "====================\n";
    echo "========================================\n";
    die();
}

function elasticApmAssertSame($expected, $actual, string $expr)
{
    if ( $expected === $actual ) return;

    elasticApmOnAssertFailure("the same", $expected, $actual, $expr);
}

function elasticApmAssertEqual($expected, $actual, string $expr)
{
    if ( $expected == $actual ) return;

    elasticApmOnAssertFailure("equal", $expected, $actual, $expr);
}

function elasticApmIsOsWindows(): bool
{
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}
