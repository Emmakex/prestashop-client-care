<?php
// A PHP Error thrown by PrestaShop's email transport must not crash the ticket request.
define('_PS_VERSION_', '9.0.0');
class Validate { public static function isEmail($e) {return true;} }
class Language { public static function getIsoById($i) {return 'es';} }
class Mail { public static function Send(...$args) {throw new Error('SIMULATED SMTP/PHP ERROR: must be caught');} }
class PrestaShopLogger {public static function addLog(...$args) {throw new Error('SIMULATED LOGGER ERROR');} }
class FakeModule {public function getLocalPath(){return '/tmp/psclientcare/';} public function getRecipient(){return 'test@example.com';} }
class Configuration {public static function get($k){return 1;} }
require __DIR__ . '/../psclientcare/classes/PsccMailer.php';
$recipient = new PsccMailer(new FakeModule());
$ticket=['id_pscc_ticket'=>1,'reference'=>'TEST123','firstname'=>'Demo','lastname'=>'Customer','email'=>'customer@example.com','subject'=>'Broken', 'id_order'=>7];
$result=$recipient->notifyStaff($ticket, [], [], 'El pedido llegó dañado');
if ($result !== false) {throw new Exception('Mail failure did not return false');}
echo "PASS mail Throwable failure isolated (including logger exception)\n";
