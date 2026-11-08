<?php

require_once dirname(__FILE__) . '/StatssearchTestCase.php';
require_once dirname(__FILE__) . '/support/Probe.php';

class StatssearchReportTest extends StatssearchTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $module = new statssearch();
        $this->assertTrue($module->install());
    }

    private function newModule()
    {
        return new statssearch();
    }

    public function testEmptyCatalogShowsTheNoRepeatMessage()
    {
        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $this->assertContains('Shop search', $html);
        $this->assertContains(
            'Cannot find any keywords that have been searched for more than once.',
            $html
        );
        $this->assertNotContains('<table', $html);
        $this->assertNotContains('export=1', $html);
        $this->assertNotContains('engine:pie', $html);
    }

    public function testOneSearchIsNotListed()
    {
        $this->insertStatsRow(1, 1, 'coat', 1, '2020-06-15 12:00:00');
        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $this->assertContains(
            'Cannot find any keywords that have been searched for more than once.',
            $html
        );

        $probe = new StatssearchGraphProbe();
        $probe->setEmployee(3);
        $graph = $probe->load(1);
        $this->assertTrue(is_array($graph['legend']));
        $this->assertTrue(is_array($graph['values']));
        $this->assertCount(0, $graph['legend']);
        $this->assertCount(0, $graph['values']);
    }

    public function testTableOrdersByOccurrencesAndShowsMaxResults()
    {
        $this->insertStatsRow(1, 1, 'dress', 2, '2020-06-10 10:00:00');
        $this->insertStatsRow(1, 1, 'dress', 9, '2020-06-11 10:00:00');
        $this->insertStatsRow(1, 1, 'dress', 4, '2020-06-12 10:00:00');
        $this->insertStatsRow(1, 1, 'hat', 1, '2020-06-13 10:00:00');
        $this->insertStatsRow(1, 1, 'hat', 1, '2020-06-14 10:00:00');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertSame(
            array('dress', '3', '9', 'hat', '2', '1'),
            $cells
        );
        $this->assertContains('Keywords', $html);
        $this->assertContains('Occurrences', $html);
        $this->assertContains('Results', $html);
        $this->assertContains('engine:pie', $html);
        $this->assertNotContains('engine:column', $html);
        $this->assertContains('controller=AdminStats', $html);
        $this->assertContains('module=statssearch', $html);
        $this->assertContains('export=1', $html);
    }

    public function testKeywordsShorterThanTheMinimumAreHidden()
    {
        Configuration::set('PS_SEARCH_MINWORDLEN', 4);
        $this->insertStatsRow(1, 1, 'hat', 1, '2020-06-10 10:00:00');
        $this->insertStatsRow(1, 1, 'hat', 1, '2020-06-11 10:00:00');
        $this->insertStatsRow(1, 1, 'hat', 1, '2020-06-12 10:00:00');
        $this->insertStatsRow(1, 1, 'coat', 1, '2020-06-13 10:00:00');
        $this->insertStatsRow(1, 1, 'coat', 1, '2020-06-14 10:00:00');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertContains('coat', $cells);
        $this->assertNotContains('hat', $cells);
        $this->assertContains('panel-heading', $html);
    }

    public function testMinimumLengthCountsCharactersNotBytes()
    {
        Configuration::set('PS_SEARCH_MINWORDLEN', 3);
        $this->insertStatsRow(1, 1, 'éé', 1, '2020-06-10 10:00:00');
        $this->insertStatsRow(1, 1, 'éé', 1, '2020-06-11 10:00:00');
        $this->insertStatsRow(1, 1, 'éé', 1, '2020-06-12 10:00:00');
        $this->insertStatsRow(1, 1, 'coat', 1, '2020-06-13 10:00:00');
        $this->insertStatsRow(1, 1, 'coat', 1, '2020-06-14 10:00:00');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertContains('coat', $cells);
        $this->assertNotContains('éé', $cells);
    }

    public function testMinimumLengthDecodesHtmlEntitiesBeforeCounting()
    {
        Configuration::set('PS_SEARCH_MINWORDLEN', 5);
        $this->insertStatsRow(1, 1, 'AT&amp;T', 1, '2020-06-10 10:00:00');
        $this->insertStatsRow(1, 1, 'AT&amp;T', 1, '2020-06-11 10:00:00');
        $this->insertStatsRow(1, 1, 'coats', 1, '2020-06-12 10:00:00');
        $this->insertStatsRow(1, 1, 'coats', 1, '2020-06-13 10:00:00');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertContains('coats', $cells);
        $this->assertNotContains('AT&T', $cells);
        $this->assertNotContains('AT&amp;T', $cells);
    }

    public function testJuneWindowKeepsItsEndpointsAndDropsNeighbors()
    {
        $this->insertStatsRow(1, 1, 'boots', 3, '2020-06-01 00:00:00');
        $this->insertStatsRow(1, 1, 'boots', 7, '2020-06-30 23:59:59');
        $this->insertStatsRow(1, 1, 'boots', 1, '2020-05-31 23:59:59');
        $this->insertStatsRow(1, 1, 'sandals', 1, '2020-07-01 00:00:00');
        $this->insertStatsRow(1, 1, 'sandals', 1, '2020-07-01 00:00:01');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertSame(array('boots', '2', '7'), $cells);
        $this->assertNotContains('sandals', $cells);
    }

    public function testRowsFromAnotherShopAreIgnored()
    {
        Context::getContext()->setShop(2, 1);
        $this->insertStatsRow(2, 1, 'linen', 1, '2020-06-10 10:00:00');
        $this->insertStatsRow(2, 1, 'linen', 1, '2020-06-11 10:00:00');
        $this->insertStatsRow(1, 1, 'linen', 1, '2020-06-12 10:00:00');
        $this->insertStatsRow(1, 1, 'linen', 1, '2020-06-13 10:00:00');
        $this->insertStatsRow(1, 1, 'linen', 1, '2020-06-14 10:00:00');
        $this->insertStatsRow(1, 1, 'linen', 1, '2020-06-15 10:00:00');
        $this->insertStatsRow(1, 1, 'linen', 1, '2020-06-16 10:00:00');
        $this->insertStatsRow(1, 1, 'silk', 1, '2020-06-17 10:00:00');
        $this->insertStatsRow(1, 1, 'silk', 1, '2020-06-18 10:00:00');
        $this->insertStatsRow(1, 1, 'silk', 1, '2020-06-19 10:00:00');

        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $cells = $this->extractTdTexts($html);
        $this->assertSame(array('linen', '2', '1'), $cells);
        $this->assertNotContains('silk', $cells);
    }

    public function testGraphUsesTheEmployeeWindowNotTheContextWindow()
    {
        $this->insertStatsRow(1, 1, 'boots', 1, '2020-06-15 12:00:00');
        $this->insertStatsRow(1, 1, 'boots', 1, '2020-06-16 12:00:00');

        $julyCounts = array(
            'k20' => 20,
            'k18' => 18,
            'k16' => 16,
            'k14' => 14,
            'k12' => 12,
            'k10' => 10,
            'k08' => 8,
            'k06' => 6,
            'k05' => 5,
            'k04' => 4,
            'k03' => 3,
        );
        foreach ($julyCounts as $keyword => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->insertStatsRow(1, 1, $keyword, 1, '2020-07-15 12:00:00');
            }
        }

        $probe = new StatssearchGraphProbe();
        $probe->setEmployee(5);
        $graph = $probe->load(1);

        $expectedLegend = array('k20', 'k18', 'k16', 'k14', 'k12', 'k10', 'k08', 'k06', 'k05', 'Others');
        $expectedValues = array(20, 18, 16, 14, 12, 10, 8, 6, 5, 7);

        $this->assertSame($expectedLegend, $graph['legend']);
        $this->assertCount(count($expectedValues), $graph['values']);
        foreach ($expectedValues as $index => $expected) {
            $this->assertTrue(is_numeric($graph['values'][$index]));
            $this->assertSame($expected, (int) $graph['values'][$index]);
        }
        $this->assertSame('Top 10 keywords', $graph['title']);
        $this->assertNotContains('boots', $graph['legend']);
        foreach ($graph['legend'] as $entry) {
            $this->assertTrue(is_string($entry));
        }
    }

    public function testNineGroupsDoNotAddAnOthersSlice()
    {
        for ($count = 10; $count >= 2; $count--) {
            $keyword = 'k' . sprintf('%02d', $count);
            for ($i = 0; $i < $count; $i++) {
                $this->insertStatsRow(1, 1, $keyword, 1, '2020-07-10 12:00:00');
            }
        }

        $probe = new StatssearchGraphProbe();
        $probe->setEmployee(5);
        $graph = $probe->load(1);
        $this->assertCount(9, $graph['legend']);
        $this->assertNotContains('Others', $graph['legend']);
        $this->assertSame('Top 10 keywords', $graph['title']);
    }

    public function testTwoGroupsDoNotAddAnOthersSlice()
    {
        for ($i = 0; $i < 4; $i++) {
            $this->insertStatsRow(1, 1, 'coat', 1, '2020-07-10 12:00:00');
        }
        for ($i = 0; $i < 2; $i++) {
            $this->insertStatsRow(1, 1, 'hat', 1, '2020-07-11 12:00:00');
        }

        $probe = new StatssearchGraphProbe();
        $probe->setEmployee(5);
        $graph = $probe->load(1);
        $this->assertSame(array('coat', 'hat'), $graph['legend']);
        $this->assertSame(array(4, 2), array_map('intval', $graph['values']));
        $this->assertNotContains('Others', $graph['legend']);
    }

    public function testExportChildEmitsGraphRowsForTheContextWindow()
    {
        $script = dirname(__FILE__) . '/support/child-export.php';
        $env = array(
            'STATSSEARCH_DB_HOST' => getenv('STATSSEARCH_DB_HOST'),
            'STATSSEARCH_DB_PORT' => getenv('STATSSEARCH_DB_PORT'),
            'STATSSEARCH_DB_NAME' => getenv('STATSSEARCH_DB_NAME'),
            'STATSSEARCH_DB_USER' => getenv('STATSSEARCH_DB_USER'),
            'STATSSEARCH_DB_PASSWORD' => getenv('STATSSEARCH_DB_PASSWORD'),
        );
        $result = $this->runPhpChild($env, $script);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('exited', $result['sentinel']);
        $this->assertNoPhpDiagnostics($result['stdout'], $result['stderr']);
        $this->assertNotContains('panel-heading', $result['stdout']);

        $lines = array();
        foreach (explode("\n", trim($result['stdout'])) as $line) {
            if ($line === '') {
                continue;
            }
            $parts = explode("\t", $line);
            $this->assertCount(2, $parts);
            $lines[$parts[0]] = (int) $parts[1];
        }
        $this->assertSame(array('boots' => 4, 'hat' => 2), $lines);
        $this->assertArrayNotHasKey('k20', $lines);
        $this->assertArrayNotHasKey('Others', $lines);
    }

    public function testExportFlagZeroRendersTheTable()
    {
        $this->insertStatsRow(1, 1, 'boots', 1, '2020-06-10 10:00:00');
        $this->insertStatsRow(1, 1, 'boots', 1, '2020-06-11 10:00:00');
        $_GET['export'] = '0';
        $html = $this->newModule()->hookDisplayAdminStatsModules();
        $this->assertContains('boots', $html);
        $this->assertContains('panel-heading', $html);
    }
}
