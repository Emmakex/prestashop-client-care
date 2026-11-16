<?php
/* Simulate re-sending the same customer POST without PrestaShop/SQL server. */
define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');
define('_MYSQL_ENGINE_', 'InnoDB');
function pSQL($str, $allowHtml = false) {return (string) $str;}
function check($ok, $message) {if (!$ok) {fwrite(STDERR, "FAILED: $message\n"); exit(1);}}
class Db
{
    private static $instance;
    public $submissions = [];
    public $tickets = [];
    public $messages = [];
    public $products = [];
    public $id = 0;
    public $snapshot;
    public static function getInstance() {if (!self::$instance) {self::$instance = new self();}return self::$instance;}
    public function execute($sql)
    {
        if ($sql === 'START TRANSACTION') {$this->snapshot = [$this->submissions, $this->tickets, $this->messages, $this->products];}
        if ($sql === 'ROLLBACK' && $this->snapshot) {[$this->submissions, $this->tickets, $this->messages, $this->products] = $this->snapshot;}
        return true;
    }
    public function insert($table, $data)
    {
        if ($table === 'pscc_submission') {
            if (isset($this->submissions[$data['request_key']])) {return false;}
            $this->submissions[$data['request_key']] = $data;
            return true;
        }
        $this->id++;
        if ($table === 'pscc_ticket') {$this->tickets[$this->id] = $data;}
        elseif ($table === 'pscc_message') {$this->messages[$this->id] = $data;}
        elseif ($table === 'pscc_ticket_product') {$this->products[] = $data;}
        else {return false;}
        return true;
    }
    public function Insert_ID() {return $this->id;}
    public function update($table, $values, $where)
    {
        if ($table === 'pscc_submission') {
            if (!preg_match("/request_key = '([a-f0-9]{32})'/", $where, $m)) {return false;}
            $this->submissions[$m[1]]['id_result'] = $values['id_result'];
        }
        return true;
    }
    public function getValue($sql)
    {
        if (!preg_match("/request_key = '([a-f0-9]{32})'/", $sql, $m)) {return false;}
        $s = $this->submissions[$m[1]] ?? null;
        if (!$s) {return false;}
        foreach (['id_shop', 'id_customer', 'id_target'] as $key) {
            if (!preg_match('/' . $key . ' = (\d+)/', $sql, $match) || (int) $s[$key] !== (int) $match[1]) {return false;}
        }
        if (!preg_match("/action = '(create|reply)'/", $sql, $m) || $s['action'] !== $m[1]) {return false;}
        return $s['id_result'];
    }
}
require __DIR__ . '/../psclientcare/classes/PsccSubmission.php';
require __DIR__ . '/../psclientcare/classes/PsccRepository.php';
$db=Db::getInstance();
$key=PsccSubmission::newKey();
check(PsccSubmission::validKey($key), 'random key is valid');
check(!PsccSubmission::validKey('not-a-key'), 'invalid key rejected');
check(PsccSubmission::find($key, 1, 3, 'create', 7)===0, 'no existing ticket');
$result=PsccRepository::createTicket(7, 3, 1, 'Caja dañada', 'Producto roto durante transporte', [22], $key);
check(is_array($result) && $result['reused']===false && $result['ticket_id']>0, 'initial ticket saved');
$again=PsccRepository::createTicket(7, 3, 1, 'Caja dañada', 'Producto roto durante transporte', [22], $key);
check($again['ticket_id']===$result['ticket_id'] && $again['reused']===true, 'same POST returns existing ticket');
check(count($db->tickets)===1 && count($db->messages)===1, 'only one ticket and first message persisted');
check(PsccSubmission::find($key, 1, 44, 'create', 7)===0, 'different customer cannot see result');
$replyKey=PsccSubmission::newKey();
$reply=PsccRepository::createReply($result['ticket_id'],3,1,'El pedido está incompleto',$replyKey,'open');
check($reply['reused']===false && $reply['message_id']>0, 'reply saved');
$replyAgain=PsccRepository::createReply($result['ticket_id'],3,1,'El pedido está incompleto',$replyKey,'open');
check($replyAgain['reused']===true && $replyAgain['message_id']===$reply['message_id'], 'same reply returns original');
check(count($db->messages)===2 && count($db->submissions)===2, 'only one reply stored');
echo "PASS: atomic idempotency for ticket and reply\n";
