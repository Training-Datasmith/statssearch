<?php

require_once dirname(__FILE__) . '/StatssearchTestCase.php';

class StatssearchRecordTest extends StatssearchTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $module = new statssearch();
        $module->install();
    }

    public function testRecordsShopKeywordResultAndDatabaseTimestamp()
    {
        Context::getContext()->setShop(4, 7);
        $module = new statssearch();

        $before = $this->databaseNow();
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $before);

        $module->hookActionSearch(array('expr' => 'red shoes', 'total' => 15));

        $after = $this->databaseNow();
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $after);

        $rows = $this->fetchStatsRows();
        $this->assertCount(1, $rows);
        $this->assertSame('red shoes', $rows[0]['keywords']);
        $this->assertTrue(is_numeric($rows[0]['results']));
        $this->assertSame(15, (int) $rows[0]['results']);
        $this->assertSame('4', (string) $rows[0]['id_shop']);
        $this->assertSame('7', (string) $rows[0]['id_shop_group']);
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rows[0]['date_add']);
        $this->assertGreaterThanOrEqual($before, $rows[0]['date_add']);
        $this->assertLessThanOrEqual($after, $rows[0]['date_add']);
    }

    public function testStoresQuotesBackslashesPercentAndUnderscore()
    {
        Context::getContext()->setShop(1, 1);
        $module = new statssearch();
        $keywords = array('O\'Brien\\path', '100%_off', '42');

        foreach ($keywords as $keyword) {
            $module->hookActionSearch(array('expr' => $keyword, 'total' => 1));
        }

        $rows = $this->fetchStatsRows();
        $this->assertCount(3, $rows);
        $this->assertSame($keywords[0], $rows[0]['keywords']);
        $this->assertSame($keywords[1], $rows[1]['keywords']);
        $this->assertSame($keywords[2], $rows[2]['keywords']);
    }

    public function testCastsResultCountAndStripsHtmlTagsOnWrite()
    {
        $module = new statssearch();
        $module->hookActionSearch(array('expr' => '<b>linen</b>', 'total' => '12items'));
        $module->hookActionSearch(array('expr' => 'cotton', 'total' => 'foo'));

        $rows = $this->fetchStatsRows();
        $this->assertCount(2, $rows);
        $this->assertTrue(is_string($rows[0]['keywords']));
        $this->assertSame('linen', $rows[0]['keywords']);
        $this->assertTrue(is_numeric($rows[0]['results']));
        $this->assertSame(12, (int) $rows[0]['results']);
        $this->assertTrue(is_numeric($rows[1]['results']));
        $this->assertSame(0, (int) $rows[1]['results']);
    }

    public function testStoresAmpersandComparisonAndUtf8Unchanged()
    {
        $module = new statssearch();
        $keywords = array('AT&T', '5 < 3', 'café');
        foreach ($keywords as $keyword) {
            $module->hookActionSearch(array('expr' => $keyword, 'total' => 1));
        }

        $rows = $this->fetchStatsRows();
        $this->assertCount(3, $rows);
        foreach ($rows as $index => $row) {
            $this->assertTrue(is_string($row['keywords']));
            $this->assertSame($keywords[$index], $row['keywords']);
        }
    }

    public function testEachSearchInsertsItsOwnRow()
    {
        $module = new statssearch();
        $module->hookActionSearch(array('expr' => 'linen', 'total' => 1));
        $module->hookActionSearch(array('expr' => 'linen', 'total' => 2));

        $this->assertSame(2, $this->fetchStatsRowCount());
    }
}
