<?php

if (!function_exists('pSQL')) {
    function pSQL($string, $htmlOK = false)
    {
        return Db::getInstance()->escape($string, $htmlOK);
    }
}

class StatssearchTestHarness
{
    public static $parentInstallResult = true;
    public static $parentUninstallResult = true;
    public static $nextHookFailure = null;
    public static $registeredHooks = array();
    public static $unregisteredHooks = array();

    public static function reset()
    {
        self::$parentInstallResult = true;
        self::$parentUninstallResult = true;
        self::$nextHookFailure = null;
        self::$registeredHooks = array();
        self::$unregisteredHooks = array();
        Configuration::reset();
        Context::reset();
    }

    public static function hooksForModule($name)
    {
        if (!isset(self::$registeredHooks[$name])) {
            return array();
        }

        return self::$registeredHooks[$name];
    }
}

class Db
{
    /** @var Db */
    private static $instance;

    /** @var mysqli */
    private $link;

  private function __construct()
    {
        $host = getenv('STATSSEARCH_DB_HOST');
        $port = (int) getenv('STATSSEARCH_DB_PORT');
        $name = getenv('STATSSEARCH_DB_NAME');
        $user = getenv('STATSSEARCH_DB_USER');
        $pass = getenv('STATSSEARCH_DB_PASSWORD');

        if ($host === false || $host === '') {
            fwrite(STDERR, "STATSSEARCH_DB_HOST is not set.\n");
            exit(1);
        }

        $this->link = @mysqli_connect($host, $user, $pass, $name, $port > 0 ? $port : 3306);
        if (!$this->link) {
            fwrite(STDERR, 'mysqli_connect failed: ' . mysqli_connect_error() . "\n");
            exit(1);
        }

        mysqli_set_charset($this->link, 'utf8');
        mysqli_query($this->link, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }

    public static function getInstance($master = true)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getLink()
    {
        return $this->link;
    }

    public function execute($sql)
    {
        return mysqli_query($this->link, $sql) !== false;
    }

    public function executeS($sql)
    {
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            return false;
        }

        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);

        return $rows;
    }

    public function getValue($sql)
    {
        $result = $this->executeS($sql);
        if ($result === false || count($result) === 0) {
            return false;
        }
        $first = reset($result[0]);
        if ($first === false && is_array($result[0])) {
            $first = reset($result[0]);
        }

        return $first;
    }

    public function escape($string, $html_ok = false)
    {
        if (!is_string($string)) {
            return $string;
        }

        $string = mysqli_real_escape_string($this->link, $string);
        if (!$html_ok) {
            $string = strip_tags($string);
        }

        return $string;
    }
}

class Configuration
{
    private static $values = array(
        'PS_SEARCH_MINWORDLEN' => 3,
    );

    public static function get($key)
    {
        if (!isset(self::$values[$key])) {
            return null;
        }

        return self::$values[$key];
    }

    public static function set($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function reset()
    {
        self::$values = array(
            'PS_SEARCH_MINWORDLEN' => 3,
        );
    }
}

class Employee
{
    public $id;
    public $stats_date_from;
    public $stats_date_to;

    public function __construct($id, $from, $to)
    {
        $this->id = (int) $id;
        $this->stats_date_from = $from;
        $this->stats_date_to = $to;
    }
}

class ShopContext
{
    public $id = 1;
    public $id_shop_group = 1;
}

class Context
{
    /** @var Context */
    private static $instance;

    /** @var ShopContext */
    public $shop;

    /** @var Employee */
    public $employee;

    private function __construct()
    {
        $this->shop = new ShopContext();
        $this->employee = new Employee(3, '2020-06-01', '2020-06-30');
    }

    public static function getContext()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
        self::getContext();
    }

    public function setEmployeeWindow($id, $from, $to)
    {
        $this->employee = new Employee($id, $from, $to);
    }

    public function setShop($id, $shopGroup)
    {
        $this->shop->id = (int) $id;
        $this->shop->id_shop_group = (int) $shopGroup;
    }
}

class Shop
{
    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($alias) {
            $alias .= '.';
        }

        $shopId = (int) Context::getContext()->shop->id;

        return ' AND ' . $alias . 'id_shop IN (' . $shopId . ') ';
    }
}

class Tools
{
    public static function getValue($key, $default = false)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function strlen($str)
    {
        if (is_array($str)) {
            return false;
        }
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, 'UTF-8');
        }

        return strlen($str);
    }

    public static function safeOutput($string)
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;

    /** @var Context */
    public $context;

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    protected function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return $id;
    }

    public function install()
    {
        return StatssearchTestHarness::$parentInstallResult;
    }

    public function uninstall()
    {
        return StatssearchTestHarness::$parentUninstallResult;
    }

    public function registerHook($hookName)
    {
        if (StatssearchTestHarness::$nextHookFailure === $hookName) {
            return false;
        }
        if (!isset(StatssearchTestHarness::$registeredHooks[$this->name])) {
            StatssearchTestHarness::$registeredHooks[$this->name] = array();
        }
        StatssearchTestHarness::$registeredHooks[$this->name][] = $hookName;

        return true;
    }

    public function unregisterHook($hookName)
    {
        StatssearchTestHarness::$unregisteredHooks[] = $hookName;

        return true;
    }
}

class ModuleGraph extends Module
{
    protected $_employee;
    protected $_values = array();
    protected $_legend = array();
    protected $_titles = array('main' => null, 'x' => null, 'y' => null);

    public function setEmployee($id_employee)
    {
        $employees = self::employeeMap();
        $id = (int) $id_employee;
        if (isset($employees[$id])) {
            $this->_employee = $employees[$id];
        }
    }

    public function getDate()
    {
        return static::getDateBetween($this->_employee);
    }

    public static function getDateBetween($employee = null)
    {
        if (!$employee) {
            $employee = Context::getContext()->employee;
        }

        return ' \'' . pSQL($employee->stats_date_from) . ' 00:00:00\' AND \'' . pSQL($employee->stats_date_to) . ' 23:59:59\' ';
    }

    protected static function employeeMap()
    {
        return array(
            3 => new Employee(3, '2020-06-01', '2020-06-30'),
            5 => new Employee(5, '2020-07-01', '2020-07-31'),
        );
    }

    public function engine($params)
    {
        $type = isset($params['type']) ? $params['type'] : 'column';

        return 'engine:' . $type;
    }

    protected function csvExport($datas)
    {
        if (!isset($datas['type']) || $datas['type'] !== 'pie') {
            exit(1);
        }

        $layers = isset($datas['layers']) ? $datas['layers'] : 1;
        $context = Context::getContext();
        $this->setEmployee($context->employee->id);
        $this->_legend = array();
        $this->_values = array();
        $this->getData($layers);

        foreach ($this->_legend as $index => $legend) {
            $value = isset($this->_values[$index]) ? $this->_values[$index] : 0;
            echo $legend . "\t" . $value . "\n";
        }

        exit(0);
    }
}
