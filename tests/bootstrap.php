<?php

error_reporting(-1);
ini_set('display_errors', '1');

if (!extension_loaded('mbstring')) {
    fwrite(STDERR, "mbstring extension is required.\n");
    exit(1);
}

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "mysqli extension is required.\n");
    exit(1);
}

define('_PS_VERSION_', '1.7.8.7');
define('_DB_PREFIX_', 'ps_');
define('_MYSQL_ENGINE_', 'InnoDB');

if (!defined('_PS_USE_SQL_SLAVE_')) {
    define('_PS_USE_SQL_SLAVE_', false);
}

$root = dirname(__DIR__);

require_once $root . '/tests/support/PrestaShopStub.php';
require_once $root . '/statssearch.php';
