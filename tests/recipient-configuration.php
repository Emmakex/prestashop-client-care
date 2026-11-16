<?php
/**
 * Contract check: incident recipient remains configurable for future automations.
 * Email transport is always delegated to PrestaShop's native Mail::Send.
 */
$root = dirname(__DIR__) . '/psclientcare';
$main = file_get_contents($root . '/psclientcare.php');
$admin = file_get_contents($root . '/classes/PsccAdmin.php');
$mailer = file_get_contents($root . '/classes/PsccMailer.php');

$checks = [
    'recipient configuration key' => strpos($main, "const CONFIG_RECIPIENT = 'PSCC_RECIPIENT'") !== false,
    'recipient accessor' => strpos($main, 'function getRecipient()') !== false,
    'recipient configuration persistence' => strpos($admin, 'Configuration::updateValue(Psclientcare::CONFIG_RECIPIENT, $email)') !== false,
    'recipient editable input' => strpos($admin, 'name="pscc_recipient"') !== false,
    'incident routed to configured recipient' => strpos($mailer, '$this->module->getRecipient()') !== false,
    'native email transport' => strpos($mailer, 'Mail::Send(') !== false,
];
foreach ($checks as $name => $result) {
    if (!$result) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
}
echo "PASS incident recipient and native mail contract\n";
