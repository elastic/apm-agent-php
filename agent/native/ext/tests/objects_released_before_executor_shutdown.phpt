--TEST--
PHP objects held by the agent (last error backtrace, last exception) are released before executor shutdown (GH-1509)
--ENV--
ELASTIC_APM_ENABLED=true
ELASTIC_APM_LOG_LEVEL_STDERR=OFF
--INI--
elastic_apm.enabled = 1
elastic_apm.bootstrap_php_part_file=../../php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);

class Holder
{
    public array $data;

    public function __construct()
    {
        $this->data = [new stdClass(), [new stdClass()]];
    }

    public function raise(Holder $arg): void
    {
        // Backtrace captured by the agent for this error references objects (object and args)
        trigger_error('captured warning', E_USER_WARNING);
    }
}

$holder = new Holder();
$holder->raise(new Holder());

try {
    throw new RuntimeException('captured exception');
} catch (RuntimeException $e) {
    // the agent keeps a reference to the thrown object until the end of the request
}

echo 'Test completed', PHP_EOL;
?>
--EXPECTF--
%AWarning: captured warning in %s on line %d
%ATest completed
