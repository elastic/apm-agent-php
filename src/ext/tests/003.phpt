--TEST--
Check if elasticapm provides transaction_id and trace_id
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
printf("%d\n", strlen(elasticApmGetCurrentTransactionId()));
printf("%d\n", strlen(elasticApmGetCurrentTraceId()));
?>
--EXPECT--
16
32
