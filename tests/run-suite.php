<?php

error_reporting(-1);

$root = dirname(__DIR__);
$expectedFiles = array(
    $root . '/tests/DirectoryGuardTest.php',
    $root . '/tests/StatssearchModuleTest.php',
    $root . '/tests/StatssearchRecordTest.php',
    $root . '/tests/StatssearchReportTest.php',
);

$found = glob($root . '/tests/*Test.php');
sort($found);
if ($found !== $expectedFiles) {
    fwrite(STDERR, "Test file list drift.\nExpected:\n" . implode("\n", $expectedFiles) . "\nFound:\n" . implode("\n", $found) . "\n");
    exit(1);
}

$phpunit = getenv('STATSSEARCH_PHPUNIT');
if (!$phpunit || !is_file($phpunit)) {
    fwrite(STDERR, "STATSSEARCH_PHPUNIT must point to verified phpunit-4.8.36.phar\n");
    exit(1);
}

$work = getenv('STATSSEARCH_WORK_DIR');
if (!$work || !is_dir($work)) {
    fwrite(STDERR, "STATSSEARCH_WORK_DIR must be set to an existing temp directory\n");
    exit(1);
}

$expectedTestCount = 35;
$seeds = array(20261108, 20261109);
$bootstrap = $root . '/tests/bootstrap.php';

function fisherYatesOrder($files, $seed)
{
    $items = array_values($files);
    $count = count($items);
    mt_srand($seed);
    for ($i = $count - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $tmp = $items[$i];
        $items[$i] = $items[$j];
        $items[$j] = $tmp;
    }

    return $items;
}

function writePhpUnitXml($path, $bootstrap, $files)
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $phpunit = $doc->createElement('phpunit');
    $phpunit->setAttribute('bootstrap', $bootstrap);
    $phpunit->setAttribute('colors', 'false');
    $phpunit->setAttribute('verbose', 'true');
    $phpunit->setAttribute('convertErrorsToExceptions', 'true');
    $phpunit->setAttribute('convertNoticesToExceptions', 'true');
    $phpunit->setAttribute('convertWarningsToExceptions', 'true');
    $phpunit->setAttribute('beStrictAboutTestsThatDoNotTestAnything', 'true');
    $phpunit->setAttribute('beStrictAboutOutputDuringTests', 'true');
    $doc->appendChild($phpunit);

    $testsuites = $doc->createElement('testsuites');
    $phpunit->appendChild($testsuites);
    $suite = $doc->createElement('testsuite');
    $suite->setAttribute('name', 'module-tests');
    $testsuites->appendChild($suite);

    foreach ($files as $file) {
        $suite->appendChild($doc->createElement('file', $file));
    }

    if ($doc->save($path) === false) {
        fwrite(STDERR, "Failed to write phpunit xml.\n");
        exit(1);
    }
}

function parseOkSummary($output, $expectedCount)
{
    if (strpos($output, 'Warning:') !== false || strpos($output, 'FAILURES!') !== false) {
        return false;
    }
    if (strpos($output, 'Skipped') !== false || strpos($output, 'Incomplete') !== false || strpos($output, 'Risky') !== false) {
        return false;
    }
    if (!preg_match('/^OK \\(' . $expectedCount . ' tests, [0-9]+ assertions\\)$/m', $output, $matches)) {
        return false;
    }
    if (!preg_match('/^OK \\(' . $expectedCount . ' tests, ([0-9]+) assertions\\)$/m', $output, $countMatches)) {
        return false;
    }

    return (int) $countMatches[1];
}

function parseJunit($path, $expectedCount)
{
    if (!is_file($path)) {
        return false;
    }
    $xml = @simplexml_load_file($path);
    if ($xml === false) {
        return false;
    }
    $suite = $xml;
    if ($suite->getName() === 'testsuites' && isset($suite->testsuite[0])) {
        $suite = $suite->testsuite[0];
    }
    $tests = (int) $suite['tests'];
    $failures = (int) $suite['failures'];
    $errors = (int) $suite['errors'];

    return $tests === $expectedCount && $failures === 0 && $errors === 0;
}

$overall = true;
foreach ($seeds as $seed) {
    $order = fisherYatesOrder($expectedFiles, $seed);
    $xmlPath = $work . '/phpunit-' . $seed . '.xml';
    $junitPath = $work . '/junit-' . $seed . '.xml';
    writePhpUnitXml($xmlPath, $bootstrap, $order);

    $cmd = escapeshellarg(PHP_BINARY)
        . ' -d error_reporting=-1 '
        . escapeshellarg($phpunit)
        . ' --configuration ' . escapeshellarg($xmlPath)
        . ' --log-junit ' . escapeshellarg($junitPath);

    $outputLines = array();
    exec($cmd, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    echo "===== seed $seed =====\n";
    echo $output . "\n";

    $assertions = parseOkSummary($output, $expectedTestCount);
    $junitOk = parseJunit($junitPath, $expectedTestCount);
    if ($exitCode !== 0 || $assertions === false || $assertions < $expectedTestCount || !$junitOk) {
        $overall = false;
        fwrite(STDERR, "Seed $seed failed.\n");
    }
}

if (!$overall) {
    exit(1);
}

exit(0);
