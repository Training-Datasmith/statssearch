<?php

require_once dirname(__FILE__) . '/bootstrap.php';

abstract class StatssearchTestCase extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        StatssearchTestHarness::reset();
        $this->dropStatsTable();
        $_GET = array();
        $_POST = array();
        $_SERVER['REQUEST_URI'] = '/bo/index.php?controller=AdminStats&module=statssearch';
        Context::getContext()->setShop(1, 1);
        Context::getContext()->setEmployeeWindow(3, '2020-06-01', '2020-06-30');
        Configuration::set('PS_SEARCH_MINWORDLEN', 3);
    }

    protected function tearDown()
    {
        $this->dropStatsTable();
    }

    protected function repoRoot()
    {
        return dirname(__DIR__);
    }

    protected function dropStatsTable()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'statssearch`');
    }

    protected function tableExists()
    {
        $sql = 'SELECT COUNT(*) AS c FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = \'' . _DB_PREFIX_ . 'statssearch\'';

        $value = Db::getInstance()->getValue($sql);
        if (!is_numeric($value)) {
            $this->fail('information_schema count is not numeric: ' . var_export($value, true));
        }

        return (int) $value > 0;
    }

    protected function insertStatsRow($shopId, $shopGroupId, $keyword, $results, $dateAdd)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'statssearch` '
            . '(`id_shop`, `id_shop_group`, `keywords`, `results`, `date_add`) VALUES ('
            . (int) $shopId . ', '
            . (int) $shopGroupId . ', '
            . '\'' . pSQL($keyword, true) . '\', '
            . (int) $results . ', '
            . '\'' . pSQL($dateAdd, true) . '\')';

        $this->assertTrue(Db::getInstance()->execute($sql));
    }

    protected function fetchStatsRows()
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'statssearch` ORDER BY id_statssearch ASC'
        );
    }

    protected function fetchStatsRowCount()
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'statssearch`'
        );
    }

    protected function databaseNow()
    {
        return Db::getInstance()->getValue('SELECT NOW()');
    }

    protected function extractTdTexts($html)
    {
        $cells = array();
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $html, $matches)) {
            return $cells;
        }
        foreach ($matches[1] as $raw) {
            $cells[] = trim(strip_tags($raw));
        }

        return $cells;
    }

    protected function assertNoPhpDiagnostics($stdout, $stderr)
    {
        $combined = $stdout . "\n" . $stderr;
        $pattern = '/(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:)/';
        $this->assertSame(0, preg_match($pattern, $combined), 'PHP diagnostic in child output: ' . $combined);
    }

    protected function runPhpChild(array $env, $includePath = null)
    {
        $work = sys_get_temp_dir() . '/statssearch-child-' . bin2hex(self::randomBytes(8));
        mkdir($work, 0700, true);
        $sentinel = $work . '/sentinel.txt';
        $stdoutFile = $work . '/stdout.txt';
        $stderrFile = $work . '/stderr.txt';

        $script = $work . '/child.php';
        $code = '<?php
error_reporting(-1);
ini_set("display_errors", "1");
$sentinel = ' . var_export($sentinel, true) . ';
register_shutdown_function(function () use ($sentinel) {
    file_put_contents($sentinel, "exited");
});
';
        if ($includePath !== null) {
            $code .= '$target = ' . var_export($includePath, true) . ';
include $target;
file_put_contents($sentinel, "fell-through");
';
        }
        file_put_contents($script, $code);

        $envLines = array();
        foreach ($env as $key => $value) {
            $envLines[] = $key . '=' . $value;
        }

        $cmd = 'env ' . implode(' ', array_map('escapeshellarg', $envLines))
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1'
            . ' ' . escapeshellarg($script)
            . ' > ' . escapeshellarg($stdoutFile)
            . ' 2> ' . escapeshellarg($stderrFile);

        exec($cmd, $unused, $exitCode);

        $stdout = is_file($stdoutFile) ? file_get_contents($stdoutFile) : '';
        $stderr = is_file($stderrFile) ? file_get_contents($stderrFile) : '';
        $sentinelContent = is_file($sentinel) ? file_get_contents($sentinel) : '';

        $this->unlinkPath($work);

        return array(
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'sentinel' => $sentinelContent,
        );
    }

    protected static function randomBytes($length)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        return $bytes;
    }

    protected function unlinkPath($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->unlinkPath($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
