<?php

error_reporting(-1);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/bootstrap.php';

StatssearchTestHarness::reset();
$db = Db::getInstance();
$db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'statssearch`');

$module = new statssearch();
$module->install();

function insertRow($shop, $group, $keyword, $results, $date)
{
    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'statssearch` (`id_shop`, `id_shop_group`, `keywords`, `results`, `date_add`) VALUES ('
        . (int) $shop . ', ' . (int) $group . ', \'' . pSQL($keyword, true) . '\', ' . (int) $results . ', \'' . pSQL($date, true) . '\')';
    Db::getInstance()->execute($sql);
}

for ($i = 0; $i < 4; $i++) {
    insertRow(1, 1, 'boots', 1, '2020-06-10 10:00:00');
}
for ($i = 0; $i < 2; $i++) {
    insertRow(1, 1, 'hat', 1, '2020-06-11 10:00:00');
}
for ($i = 0; $i < 5; $i++) {
    insertRow(1, 1, 'k20', 1, '2020-07-10 10:00:00');
}

$_GET = array('export' => '1');
$_SERVER['REQUEST_URI'] = '/bo/index.php?controller=AdminStats&module=statssearch';
Context::getContext()->setEmployeeWindow(3, '2020-06-01', '2020-06-30');

$module->hookDisplayAdminStatsModules();
