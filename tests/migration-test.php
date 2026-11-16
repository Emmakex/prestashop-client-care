<?php
define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');
class Module {}
class Db {
    public static $column = false;
    public static $queries = [];
    public static function getInstance() {return new self();}
    public function executeS($sql) {self::$queries[] = $sql;return self::$column ? [['Field'=>'tracking_url']] : [];}
    public function execute($sql) {self::$queries[] = $sql;self::$column = true;return true;}
}
require __DIR__ . '/../psclientcare/psclientcare.php';
require __DIR__ . '/../psclientcare/upgrade/upgrade-0.1.2.php';
$module = (new ReflectionClass('Psclientcare'))->newInstanceWithoutConstructor();
if (!upgrade_module_0_1_2($module)) {throw new Exception('migration failed');}
if (count(Db::$queries) !== 2 || !Db::$column) {throw new Exception('migration did not ADD COLUMN');}
if (!upgrade_module_0_1_2($module)) {throw new Exception('idempotence failed');}
if (count(Db::$queries) !== 3) {throw new Exception('idempotence changed schema');}
print("PASS migration regression\n");
