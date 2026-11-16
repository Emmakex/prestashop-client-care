<?php
$root=dirname(__DIR__) . '/psclientcare';
$repo=file_get_contents($root . '/classes/PsccRepository.php');
$front=file_get_contents($root . '/controllers/front/portal.php');
$tpl=file_get_contents($root . '/views/templates/front/portal.tpl');
$admin=file_get_contents($root . '/classes/PsccAdmin.php');
$mail=file_get_contents($root . '/classes/PsccMailer.php');
$checks = [
 'order row serialization' => strpos($repo, 'FOR UPDATE') !== false,
 'one active case per order' => strpos($repo, 'getActiveTicketForOrder') !== false,
 'existing case gets additional message' => strpos($repo, 'Asunto adicional:') !== false,
 'archive is customer and shop scoped' => strpos($repo, 'archiveCustomerTicket') !== false,
 'closed replies rejected' => strpos($repo, 'Cannot reply to a closed case') !== false,
 'consolidated history' => strpos($repo, 'getOrderHistory($orderId, $customerId, $shopId)') !== false,
 'archive action' => strpos($front, 'archiveCustomerTicket') !== false,
 'archive button' => strpos($tpl, 'Cerrar y archivar incidencia') !== false,
 'customer grouped order history' => strpos($tpl, '$pscc_order_groups as $g') !== false,
 'order active CTA' => strpos($tpl, '$pscc_order_active') !== false,
 'archived readonly' => strpos($tpl, 'no añadir mensajes nuevos') !== false,
 'admin archive support' => strpos($admin, "'archived'") !== false,
 'recipient retained' => strpos($admin, 'name="pscc_recipient"') !== false,
 'native mail' => strpos($mail, 'Mail::Send(') !== false,
 'mail diagnostic' => strpos($mail, 'PSCC_LAST_MAIL_DIAGNOSTIC') !== false,
];
foreach($checks as $name=>$ok){if(!$ok){fwrite(STDERR, "FAIL: ".$name.PHP_EOL); exit(1);}}
echo "PASS archive and grouped order history".PHP_EOL;
