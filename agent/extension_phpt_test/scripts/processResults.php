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

function printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent, $unexpectedFailuresWithoutAgent, $unexpectedFailuresWithAgent) {
	$width = 70;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests allowed to fail: ".count($testsAllowedToFail).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests failed without agent: ".count($testsFailedWithoutAgent).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Unexpected failures:".PHP_EOL;
	print_r($unexpectedFailuresWithoutAgent);
	echo str_repeat("=", $width).PHP_EOL;
	echo "Tests failed with agent: ".count($testsFailedWithAgent).PHP_EOL;
	echo str_repeat("=", $width).PHP_EOL;
	echo "Unexpected failures:".PHP_EOL;
	print_r($unexpectedFailuresWithAgent);
	echo str_repeat("=", $width).PHP_EOL;
}

function getStatistics($results) {
	$stats = array();
	foreach ($results as $result) {
		$res = explode("\t", $result, 2);
		if (count($res) != 2) {
			continue;
		}
		if (!array_key_exists($res[0], $stats)) {
			$stats[$res[0]] = 0;
		}
		$stats[$res[0]]++;
	}
	return $stats;
}

function writeResultsPerTest($output, $stats, $testsAllowedToFail, $unexpectedFailures) {
	fwrite($output, "| Test result | count |".PHP_EOL);
	fwrite($output, "| --- | --- |".PHP_EOL);
	foreach ($stats as $res => $cnt) {
		fwrite($output, "| ". $res." | ". $cnt." |".PHP_EOL);
	}
	fwrite($output, "| Tests allowed to fail | ".count($testsAllowedToFail)." |".PHP_EOL);
	fwrite($output, "| Unexpected test failures | ".count($unexpectedFailures)." |".PHP_EOL);
	fwrite($output, PHP_EOL);

	if (count($unexpectedFailures) > 0) {
		fwrite($output, "#### Tests that did not pass and are outside the list of allowed to fail".PHP_EOL);

		fwrite($output, "```".PHP_EOL);
		foreach ($unexpectedFailures as $res) {
			fwrite($output, $res.PHP_EOL);
		}
		fwrite($output, "```".PHP_EOL);
	}
}

function generateMarkdownResults($outputFileName, $statsWithAgent, $statsWithoutAgent, $unexpectedFailuresWithAgent, $unexpectedFailuresWithoutAgent, $testsAllowedToFail) {
	$output = fopen($outputFileName, "w");

	if (!$output) {
		echo "Unable to open markdown output file ".$outputFileName.PHP_EOL;
		return;
	}

	fwrite($output, "# phpt tests execution summary".PHP_EOL);

	fwrite($output, "## tests executed without agent. ");
	if (count($unexpectedFailuresWithoutAgent) > 0) {
		fwrite($output, " :x: Test failed".PHP_EOL);
	} else {
		fwrite($output, " :white_check_mark: Test passed".PHP_EOL);
	}

	writeResultsPerTest($output, $statsWithoutAgent, $testsAllowedToFail, $unexpectedFailuresWithoutAgent);

	fwrite($output, PHP_EOL);
	fwrite($output, "## tests executed with agent");
	if (count($unexpectedFailuresWithAgent) > 0) {
		fwrite($output, " :x: Test failed".PHP_EOL);
	} else {
		fwrite($output, " :white_check_mark: Test passed".PHP_EOL);
	}

	writeResultsPerTest($output, $statsWithAgent, $testsAllowedToFail, $unexpectedFailuresWithAgent);

	fwrite($output, PHP_EOL);
	if (count($unexpectedFailuresWithAgent) > 0) {
		fwrite($output, "# :x: Test failed".PHP_EOL);
	} else {
		fwrite($output, "# :white_check_mark: Test passed".PHP_EOL);
	}

	fclose($output);
}

function printHelp($argc, $argv) {
	echo "Usage: ".$argv[0]." --allowed fileName --failed_with_agent fileName --failed_without_agent fileName".PHP_EOL.PHP_EOL;
	echo "      --allowed fileName               - file with set of tests allowed to fail. Required".PHP_EOL;
	echo "      --failed_with_agent fileName     - file with set of tests failed with agent injected. Required".PHP_EOL;
	echo "      --failed_without_agent fileName  - file with set of tests failed without agent injected. Required".PHP_EOL;
	echo "      --results_with_agent fileName    - set of full tests results with agent injected. Required".PHP_EOL;
	echo "      --results_without_agent fileName - set of full tests results without agent injected. Required".PHP_EOL;
	echo "      --markdown fileName              - name of the output file to generate results in markdown format. Required".PHP_EOL;
	echo "      --help                           - display this help".PHP_EOL;
}

$options = getopt("h", ["allowed:", "failed_with_agent:", "failed_without_agent:", "results_with_agent:", "results_without_agent:", "markdown:", "help"]);
if (array_key_exists("help", $options) || !array_key_exists("allowed", $options) || !array_key_exists("failed_without_agent", $options) || !array_key_exists("failed_with_agent", $options) || !array_key_exists("results_with_agent", $options) || !array_key_exists("results_without_agent", $options) || !array_key_exists("markdown", $options)) {
	printHelp($argc, $argv);
	exit(0);
}

$testsAllowedToFail = file($options["allowed"], FILE_IGNORE_NEW_LINES);
$testsFailedWithAgent = file($options["failed_with_agent"], FILE_IGNORE_NEW_LINES);
$testsFailedWithoutAgent = file($options["failed_without_agent"], FILE_IGNORE_NEW_LINES);

$testsResultsWithAgent = file($options["results_with_agent"], FILE_IGNORE_NEW_LINES);
$testsResultsWithoutAgent = file($options["results_without_agent"], FILE_IGNORE_NEW_LINES);

$unexpectedFailuresWithoutAgent = getUnexpectedFailures($testsAllowedToFail, $testsFailedWithoutAgent);
$unexpectedFailuresWithAgent = getUnexpectedFailures($testsAllowedToFail, $testsFailedWithAgent);

printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent, $unexpectedFailuresWithoutAgent, $unexpectedFailuresWithAgent);

$statsWithoutAgent = getStatistics($testsResultsWithoutAgent);
$statsWithAgent = getStatistics($testsResultsWithAgent);

generateMarkdownResults($options["markdown"], $statsWithAgent, $statsWithoutAgent, $unexpectedFailuresWithAgent, $unexpectedFailuresWithoutAgent, $testsAllowedToFail);

?>
