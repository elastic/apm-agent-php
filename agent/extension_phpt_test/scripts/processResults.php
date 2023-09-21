#!/usr/bin/env php
<?php

function getUnexpectedFailures($allowed, $failures)
{
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

function printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent, $unexpectedFailuresWithoutAgent, $unexpectedFailuresWithAgent)
{
	$width = 70;
	echo str_repeat("=", $width) . PHP_EOL;
	echo "Tests allowed to fail: " . count($testsAllowedToFail) . PHP_EOL;
	echo str_repeat("=", $width) . PHP_EOL;
	echo "Tests failed without agent: " . count($testsFailedWithoutAgent) . PHP_EOL;
	echo str_repeat("=", $width) . PHP_EOL;
	echo "Unexpected failures:" . PHP_EOL;
	print_r($unexpectedFailuresWithoutAgent);
	echo str_repeat("=", $width) . PHP_EOL;
	echo "Tests failed with agent: " . count($testsFailedWithAgent) . PHP_EOL;
	echo str_repeat("=", $width) . PHP_EOL;
	echo "Unexpected failures:" . PHP_EOL;
	print_r($unexpectedFailuresWithAgent);
	echo str_repeat("=", $width) . PHP_EOL;
}

function getStatistics($results)
{
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

// returns true on success or false on failure
function writeResultsPerTest($output, $stats, $testsAllowedToFail, $unexpectedFailures, $segfaults, $baseline)
{
	fwrite($output, "| Status | Test result | count |" . PHP_EOL);
	fwrite($output, "| --- | --- | --- |" . PHP_EOL);
	foreach ($stats as $res => $cnt) {
		fwrite($output, "|  | " . $res . " | " . $cnt . " |" . PHP_EOL);
	}
	fwrite($output, "| " . (count($segfaults) > 0 ? ":x:" : ":heavy_check_mark:") . " | Segmentation faults | " . count($segfaults) . " |" . PHP_EOL);
	fwrite($output, "|  | Tests allowed to fail | " . count($testsAllowedToFail) . " |" . PHP_EOL);
	fwrite($output, "| " . (count($unexpectedFailures) > 0 ? ":warning:" : ":heavy_check_mark:") . " | Unexpected test failures (without baseline) | " . count($unexpectedFailures) . " |" . PHP_EOL);

	$afterBaselineFailures = [];
	$afterBaselineFailures = getUnexpectedFailures($baseline, $unexpectedFailures);
	fwrite($output, "|  | Size of baseline list | " . count($baseline) . " |" . PHP_EOL);
	fwrite($output, "| " . (count($afterBaselineFailures) > 0 ? ":x:" : ":heavy_check_mark:") . " | Unexpected test failures (above baseline) | " . count($afterBaselineFailures) . " |" . PHP_EOL);

	fwrite($output, PHP_EOL);

	if (count($segfaults) > 0) {
		fwrite($output, "#### Tests with segmentation faults" . PHP_EOL);

		fwrite($output, "```" . PHP_EOL);
		foreach ($segfaults as $res) {
			fwrite($output, $res . PHP_EOL);
		}
		fwrite($output, "```" . PHP_EOL);
	}

	$retval = [];

	if (count($afterBaselineFailures) > 0) {
		fwrite($output, "#### Tests which failed and was above baseline and allowed to fail list" . PHP_EOL);

		fwrite($output, "```" . PHP_EOL);
		foreach ($afterBaselineFailures as $res) {
			fwrite($output, $res . PHP_EOL);
		}
		fwrite($output, "```" . PHP_EOL);

		$retval[] = "unexpected test failures even after supression";
	}

	if (count($segfaults) > 0) {
		$retval[] = count($segfaults) . " segmentation faults occurred";
	}

	fwrite($output, PHP_EOL);

	if (count($retval) > 0) {
		fwrite($output, "### :x: Test failed because " . implode(" and ", $retval) . PHP_EOL);
	} else {
		fwrite($output, "### :white_check_mark: Test passed" . PHP_EOL);
	}

	return count($retval) == 0;
}

function generateMarkdownResults($outputFileName, $statsWithAgent, $statsWithoutAgent, $unexpectedFailuresWithAgent, $unexpectedFailuresWithoutAgent, $testsAllowedToFail, $baselineList, $segfaultsWithAgent, $segfaultsWithoutAgent)
{
	$output = fopen($outputFileName, "w");

	if (!$output) {
		echo "Unable to open markdown output file " . $outputFileName . PHP_EOL;
		return false;
	}

	fwrite($output, "# phpt tests execution summary" . PHP_EOL);

	fwrite($output, "***".PHP_EOL);
	fwrite($output, "## Tests executed without agent. " . PHP_EOL);
	fwrite($output, PHP_EOL);

	$results[] = writeResultsPerTest($output, $statsWithoutAgent, $testsAllowedToFail, $unexpectedFailuresWithoutAgent, $segfaultsWithoutAgent, array());
	fwrite($output, PHP_EOL);
	fwrite($output, "***".PHP_EOL);
	fwrite($output, PHP_EOL);
	fwrite($output, "## Tests executed with agent. " . PHP_EOL);
	$results[] = writeResultsPerTest($output, $statsWithAgent, $testsAllowedToFail, $unexpectedFailuresWithAgent, $segfaultsWithAgent, $baselineList);

	fwrite($output, "***".PHP_EOL);
	fwrite($output, PHP_EOL . "# Summary status: " . (($results[0] && $results[1]) ? ":heavy_check_mark: PASSED" : ":x: FAILED") . PHP_EOL);

	fclose($output);

	return $results[0] && $results[1];
}

// done in bash, lets keep it for future
// function scanForSegFaults($pathToTests) {
// 	$segfaults = array();
// 	$it = new RecursiveDirectoryIterator($pathToTests);
// 	foreach(new RecursiveIteratorIterator($it) as $file) {
// 		if ($file->getExtension() != 'log') {
// 			continue;
// 		}
// 		$fileobj = $file->openFile('r');
// 		while (!$fileobj->eof()) {
// 			if (str_contains($fileobj->current(), "Segmentation fault (core dumped)")) {
// 				$phptFileName = $fileobj->getPath()."/".$fileobj->getBasename('.'.$fileobj->getExtension()).".phpt";
// 				if (file_exists($phptFileName)) {
// 					$segfaults[] = $phptFileName;
// 				}
// 			}
// 			$fileobj->next();
// 		}
// 		$fileobj = null;
// 	}
// 	return $segfaults;
// }

function printHelp($argc, $argv)
{
	echo "Usage: " . $argv[0] . " ... " . PHP_EOL . PHP_EOL;
	echo "      --allowed fileName                 - file with set of tests allowed to fail. Mostly flaky tests failing with pure PHP. Required" . PHP_EOL;
	echo "      --baseline fileName                - file with set of tests which will suppress failures (like test we're working on). Required" . PHP_EOL;
	echo "      --failed_with_agent fileName       - file with set of tests failed with agent injected. Required" . PHP_EOL;
	echo "      --failed_without_agent fileName    - file with set of tests failed without agent injected. Required" . PHP_EOL;
	echo "      --results_with_agent fileName      - set of full tests results with agent injected. Required" . PHP_EOL;
	echo "      --results_without_agent fileName   - set of full tests results without agent injected. Required" . PHP_EOL;
	echo "      --segfaults_with_agent fileName    - set of tests caused segmentation fault with agent injected. Required" . PHP_EOL;
	echo "      --segfaults_without_agent fileName - set of tests caused segmentation fault with agent injected. Required" . PHP_EOL;
	echo "      --markdown fileName                - name of the output file to generate results in markdown format. Required" . PHP_EOL;
	echo "      --help                             - display this help" . PHP_EOL;
}

$optTemplateRequired = ["allowed:", "baseline:", "failed_with_agent:", "failed_without_agent:", "results_with_agent:", "results_without_agent:", "markdown:", "segfaults_with_agent:", "segfaults_without_agent:"];
$optTemplate = ["help"];
$options = getopt("h", array_merge($optTemplate, $optTemplateRequired));

$missingOptions = "";
foreach ($optTemplateRequired as $opt) {
	if (!array_key_exists(substr($opt, 0, -1), $options)) {
		$missingOptions .= substr($opt, 0, -1) . " ";
	}
}
if (strlen($missingOptions) > 0) {
	echo PHP_EOL . "Error, missing required parameters: " . $missingOptions . PHP_EOL . PHP_EOL;
	printHelp($argc, $argv);
	exit(1);
}

if (array_key_exists("help", $options)) {
	printHelp($argc, $argv);
	exit(0);
}

$testsAllowedToFail = file($options["allowed"], FILE_IGNORE_NEW_LINES);
$testsFailedWithAgent = file($options["failed_with_agent"], FILE_IGNORE_NEW_LINES);
$testsFailedWithoutAgent = file($options["failed_without_agent"], FILE_IGNORE_NEW_LINES);

$testsResultsWithAgent = file($options["results_with_agent"], FILE_IGNORE_NEW_LINES);
$testsResultsWithoutAgent = file($options["results_without_agent"], FILE_IGNORE_NEW_LINES);

$segfaultsWithAgent = file($options["segfaults_with_agent"], FILE_IGNORE_NEW_LINES);
$segfaultsWithoutAgent = file($options["segfaults_without_agent"], FILE_IGNORE_NEW_LINES);

$baselineList = file($options["baseline"], FILE_IGNORE_NEW_LINES);


$unexpectedFailuresWithoutAgent = getUnexpectedFailures($testsAllowedToFail, $testsFailedWithoutAgent);
$unexpectedFailuresWithAgent = getUnexpectedFailures($testsAllowedToFail, $testsFailedWithAgent);

printSummary($testsAllowedToFail, $testsFailedWithoutAgent, $testsFailedWithAgent, $unexpectedFailuresWithoutAgent, $unexpectedFailuresWithAgent);

$statsWithoutAgent = getStatistics($testsResultsWithoutAgent);
$statsWithAgent = getStatistics($testsResultsWithAgent);

$result = generateMarkdownResults($options["markdown"], $statsWithAgent, $statsWithoutAgent, $unexpectedFailuresWithAgent, $unexpectedFailuresWithoutAgent, $testsAllowedToFail, $baselineList, $segfaultsWithAgent, $segfaultsWithoutAgent);

exit($result ? 0 : 1);

?>