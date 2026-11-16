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
    public function getRow($sql, $useCache = true)
    {
        if (strpos($sql, 'SELECT o.id_order FROM') !== false) {
            // In test harness, only 7 belongs to customer #3/shop #1.
            return strpos($sql, 'o.id_order = 7') !== false && strpos($sql, 'o.id_customer = 3') !== false
                && strpos($sql, 'o.id_shop = 1') !== false ? ['id_order' => 7] : false;
        }
        if (strpos($sql, 'SELECT t.id_pscc_ticket, t.status FROM') !== false) {
            foreach ($this->tickets as $id => $ticket) {
                if ($ticket['id_order'] === 7 && $ticket['id_customer'] === 3
                    && $ticket['id_shop'] === 1 && in_array($ticket['status'], ['open', 'in_progress'], true)) {
                    return ['id_pscc_ticket' => $id, 'status' => $ticket['status']];
                }
            }
            return false;
        }
        if (strpos($sql, 'SELECT status FROM') !== false) {
            preg_match('/id_pscc_ticket = (\\d+)/', $sql, $m);
            $item = $this->tickets[(int)($m[1] ?? 0)] ?? false;
            return $item && strpos($sql, 'id_customer = 3') !== false
                && strpos($sql, 'id_shop = 1') !== false ? ['status' => $item['status']] : false;
        }
        throw new Exception('Unexpected query ' . $sql);
    }
    public function update($table, $values, $where)
    {
        if ($table === 'pscc_ticket') {
            preg_match('/id_pscc_ticket = (\\d+)/', $where, $m);
            $id = (int)($m[1] ?? 0);
            if (!isset($this->tickets[$id])) {return false;}
            if (isset($values['status']) && strpos($where, "status IN") !== false
                && !in_array($this->tickets[$id]['status'], ['open','in_progress'], true)) {return true;}
            $this->tickets[$id] = array_merge($this->tickets[$id], $values);
            return true;
        }
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
// A new request for the SAME order adds a message instead of opening another case.
$appendKey=PsccSubmission::newKey();
$appended=PsccRepository::createTicket(7,3,1,'Más información','También falta un artículo',[22],$appendKey);
check($appended['appended'] === true && $appended['ticket_id'] === $result['ticket_id'], 'same order appends to active case');
check(count($db->tickets) === 1 && count($db->messages) === 2, 'second submission does not create second case');
$replyKey=PsccSubmission::newKey();
$reply=PsccRepository::createReply($result['ticket_id'],3,1,'El pedido está incompleto',$replyKey,'open');
check($reply['reused']===false && $reply['message_id']>0, 'reply saved');
$replyAgain=PsccRepository::createReply($result['ticket_id'],3,1,'El pedido está incompleto',$replyKey,'open');
check($replyAgain['reused']===true && $replyAgain['message_id']===$reply['message_id'], 'same reply returns original');
check(count($db->messages)===3 && count($db->submissions)===3, 'only one reply stored');
// Archive closes the existing conversation and remains available in history.
check(PsccRepository::archiveCustomerTicket($result['ticket_id'],3,1), 'customer may archive own case');
check($db->tickets[$result['ticket_id']]['status']==='archived', 'case status archived');
$closedReply=PsccRepository::createReply($result['ticket_id'],3,1,'Please reopen',PsccSubmission::newKey(),'open');
check($closedReply===false, 'archived ticket cannot accept replies');
$newKey=PsccSubmission::newKey();
$newCase=PsccRepository::createTicket(7,3,1,'New incident','A new event after archive',[],$newKey);
check($newCase['ticket_id']!==$result['ticket_id'] && $newCase['appended']===false, 'new case possible after closing old one');
check(count($db->tickets)===2 && count($db->messages)===4, 'archived ticket retains messages and new ticket separate');
echo "PASS: atomic idempotency for ticket and reply\n";
