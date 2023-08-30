#!/usr/bin/env php
<?php

function getUnexpectedFailures($allowed, $failures) {
	if (!$allowed) {
		return $failures;
	}

	$unexpectedFailures = array();
	foreach ($failures as $failure) {
		if (!in_array($failure, $allowed)) {
			$unexpectedFailures[] = $failure;
		}
	}
	return $unexpectedFailures;
}

function printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent) {
	$width = 70;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests allowed to fail: ".count($testsAllowedToFail).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests failed without agent: ".count($testsFailedWithoutAgent).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Unexpected failures:".PHP_EOL;
	print_r(getUnexpectedFailures($testsAllowedToFail, $testsFailedWithoutAgent));
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests failed with agent: ".count($testsFailedWithAgent).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Unexpected failures:".PHP_EOL;
	print_r(getUnexpectedFailures($testsAllowedToFail, $testsFailedWithAgent));
	echo str_repeat("=", $width).PHP_EOL;
}

function printHelp($argc, $argv) {
	echo "Usage: ".$argv[0]." --allowed fileName --failed_with_agent fileName --failed_without_agent fileName".PHP_EOL.PHP_EOL;
	echo "      --allowed fileName              - file with set of tests allowed to fail. Required".PHP_EOL;
	echo "      --failed_with_agent fileName    - file with set of tests failed with agent injected. Required".PHP_EOL;
	echo "      --failed_without_agent fileName - file with set of tests failed with agent injected. Required".PHP_EOL;
	echo "      --help                          - display this help".PHP_EOL;
}

$options = getopt("h", ["allowed:", "failed_with_agent:", "failed_without_agent:", "help"]);
if (array_key_exists("help", $options) || !array_key_exists("allowed", $options) || !array_key_exists("failed_without_agent", $options)  || !array_key_exists("failed_with_agent", $options)) {
	printHelp($argc, $argv);
	exit(0);
}

$fileNameAllowed = $options["allowed"];
$fileNameFailedWithAgent = $options["failed_with_agent"];
$fileNameFailedWithoutAgent = $options["failed_without_agent"];

$testsAllowedToFail = file($fileNameAllowed, FILE_IGNORE_NEW_LINES);
$testsFailedWithAgent = file($fileNameFailedWithAgent, FILE_IGNORE_NEW_LINES);
$testsFailedWithoutAgent = file($fileNameFailedWithoutAgent, FILE_IGNORE_NEW_LINES);

printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent);

?>
