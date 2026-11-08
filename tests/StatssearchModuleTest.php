<?php

require_once dirname(__FILE__) . '/StatssearchTestCase.php';

class StatssearchModuleTest extends StatssearchTestCase
{
    public function testConstructorPublishesModuleIdentity()
    {
        $module = new statssearch();

        $this->assertSame('statssearch', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.1.0', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
        $this->assertSame('Shop search', $module->displayName);
        $this->assertSame(
            'Enrich your stats, add a tab showing what keywords have been searched by your visitors.',
            $module->description
        );
        $this->assertSame('1.7.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
        $this->assertNotSame('', $module->ps_versions_compliancy['max']);
    }

    public function testModuleFileExitsWhenPsVersionIsMissing()
    {
        $target = $this->repoRoot() . '/statssearch.php';
        $result = $this->runPhpChild(array(), $target);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('exited', $result['sentinel']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('', $result['stderr']);
        $this->assertNoPhpDiagnostics($result['stdout'], $result['stderr']);
    }

    public function testInstallCreatesStatsTableAndRegistersHooks()
    {
        $module = new statssearch();
        $this->assertTrue($module->install());
        $this->assertTrue($this->tableExists());

        $columns = Db::getInstance()->executeS(
            'SELECT column_name, column_type, is_nullable, column_default, extra '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = \'' . _DB_PREFIX_ . 'statssearch\' '
            . 'ORDER BY ordinal_position ASC'
        );
        $this->assertCount(6, $columns);

        $this->assertSame('id_statssearch', $columns[0]['column_name']);
        $this->assertSame('NO', $columns[0]['is_nullable']);
        $this->assertSame('auto_increment', $columns[0]['extra']);

        $this->assertSame('keywords', $columns[3]['column_name']);
        $this->assertContains('255', $columns[3]['column_type']);

        $tableMeta = Db::getInstance()->executeS(
            'SELECT engine, table_collation FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = \'' . _DB_PREFIX_ . 'statssearch\''
        );
        $this->assertSame('InnoDB', $tableMeta[0]['engine']);
        $this->assertNotSame('', $tableMeta[0]['table_collation']);
        $collation = $tableMeta[0]['table_collation'];
        $utf8Family = (strpos($collation, 'utf8_') === 0) || (strpos($collation, 'utf8mb3_') === 0);
        $this->assertTrue($utf8Family);
        $this->assertFalse(strpos($collation, 'utf8mb4') === 0);

        $this->assertSame(
            array('actionSearch', 'displayAdminStatsModules'),
            StatssearchTestHarness::hooksForModule('statssearch')
        );
    }

    public function testInstallAbortsWhenParentInstallFails()
    {
        StatssearchTestHarness::$parentInstallResult = false;
        $module = new statssearch();
        $this->assertFalse($module->install());
        $this->assertFalse($this->tableExists());
        $this->assertSame(array(), StatssearchTestHarness::hooksForModule('statssearch'));
    }

    public function testInstallAbortsWhenActionSearchHookIsRejected()
    {
        StatssearchTestHarness::$nextHookFailure = 'actionSearch';
        $module = new statssearch();
        $this->assertFalse($module->install());
        $this->assertFalse($this->tableExists());
        $this->assertSame(array(), StatssearchTestHarness::hooksForModule('statssearch'));
    }

    public function testInstallAbortsWhenDisplayHookIsRejected()
    {
        StatssearchTestHarness::$nextHookFailure = 'displayAdminStatsModules';
        $module = new statssearch();
        $this->assertFalse($module->install());
        $this->assertFalse($this->tableExists());
        $this->assertNotContains('displayAdminStatsModules', StatssearchTestHarness::hooksForModule('statssearch'));
    }

    public function testSecondInstallFailsAndLeavesTheExistingTable()
    {
        $module = new statssearch();
        $this->assertTrue($module->install());
        $this->insertStatsRow(1, 1, 'sentinel-keyword', 0, '2020-06-15 12:00:00');
        $this->assertFalse($module->install());
        $this->assertSame(1, $this->fetchStatsRowCount());
    }

    public function testUninstallDropsTheStatsTable()
    {
        $module = new statssearch();
        $this->assertTrue($module->install());
        $this->assertTrue($module->uninstall());
        $this->assertFalse($this->tableExists());
    }

    public function testUninstallLeavesTheTableWhenParentUninstallFails()
    {
        $module = new statssearch();
        $this->assertTrue($module->install());
        $this->insertStatsRow(1, 1, 'keep-me', 1, '2020-06-15 12:00:00');
        StatssearchTestHarness::$parentUninstallResult = false;
        $this->assertFalse($module->uninstall());
        $this->assertSame(1, $this->fetchStatsRowCount());
    }

    public function testUninstallFailsWhenTheTableIsAlreadyGone()
    {
        StatssearchTestHarness::$parentUninstallResult = true;
        $module = new statssearch();
        $this->assertFalse($module->uninstall());
    }

    public function testUpgrade2002SwapsTheStatsHook()
    {
        require_once $this->repoRoot() . '/upgrade/upgrade-2.0.2.php';
        $module = new statssearch();
        $result = upgrade_module_2_0_2($module);
        $this->assertTrue($result);
        $this->assertSame(array('AdminStatsModules'), StatssearchTestHarness::$unregisteredHooks);
        $this->assertContains('displayAdminStatsModules', StatssearchTestHarness::hooksForModule('statssearch'));
    }

    public function testConfigXmlNameAndVersionMatchTheClass()
    {
        $module = new statssearch();
        $xml = simplexml_load_file($this->repoRoot() . '/config.xml');
        $this->assertNotFalse($xml);
        $this->assertSame((string) $xml->name, $module->name);
        $this->assertSame((string) $xml->version, $module->version);
        $this->assertSame((string) $xml->author, $module->author);
        $this->assertSame('0', (string) $xml->need_instance);
        $this->assertSame('0', (string) $xml->is_configurable);
        $this->assertSame('Shop search', (string) $xml->displayName);
        $this->assertSame('analytics_stats', (string) $xml->tab);
        $this->assertSame('administration', $module->tab);
    }
}
