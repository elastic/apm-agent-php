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
ini_set('elasticapm.enable', 1);
ini_set('elasticapm.service_name', 'test');
printf("%d\n", strlen(elasticapm_get_transaction_id()));
printf("%d\n", strlen(elasticapm_get_trace_id()));
?>
--EXPECT--
16
32
