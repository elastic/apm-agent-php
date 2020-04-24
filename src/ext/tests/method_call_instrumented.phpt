--TEST--
Intercept calls to PHP class method (including capturing parameters and return value)
--SKIPIF--
<?php
if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.' Extension elasticapm must be loaded!' );
?>
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

$pdoExecArg = "CREATE TABLE IF NOT EXISTS messages (
                  id INTEGER PRIMARY KEY,
                  message TEXT,
                  time INTEGER)";
$pdoExecExpectedRetVal = 0;

$preHook_invoked = false;

function preHook($interceptedCallArg): void
{
    global $preHook_invoked;
    $preHook_invoked = true;

    global $pdoExecArg;
    elasticApmAssertEqual('$interceptedCallArg', $interceptedCallArg, $pdoExecArg);
}

$postHook_invoked = false;

function postHook($interceptedRetVal): void
{
    global $postHook_invoked;
    $postHook_invoked = true;

    global $pdoExecExpectedRetVal;
    elasticApmAssertSame('$interceptedRetVal', $interceptedRetVal, $pdoExecExpectedRetVal);
}

function actual_test(): void
{
    global $preHook_invoked;
    global $postHook_invoked;
    global $pdoExecArg;
    global $pdoExecExpectedRetVal;

    $interceptRetVal = elasticapm_intercept_calls_to_method(
        "PDO",
        "exec",
        "preHook",
        "postHook"
    );
    elasticApmAssertSame('$interceptRetVal', $interceptRetVal, true);

    elasticApmAssertSame('$preHook_invoked', $preHook_invoked, false);
    elasticApmAssertSame('$postHook_invoked', $postHook_invoked, false);

    $pdo = new PDO('sqlite::memory:');
    $pdoExecActualRetVal = $pdo->exec($pdoExecArg);

    elasticApmAssertSame('$pdoExecActualRetVal', $pdoExecActualRetVal, $pdoExecExpectedRetVal);

    elasticApmAssertSame('$preHook_invoked', $preHook_invoked, true);
    elasticApmAssertSame('$postHook_invoked', $postHook_invoked, true);
}

// It's not clear how load `pdo_sqlite' extension on Linux for .phpt tests
if (elasticApmIsOsWindows())
{
    if ( ! extension_loaded( 'pdo_sqlite' ) ) die( 'skip'.' Extension pdo_sqlite must be loaded!' );
    actual_test();
}

echo 'Test completed'
?>
--EXPECT--
Test completed
