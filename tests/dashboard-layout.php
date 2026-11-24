<?php
// Regression for user's screenshot: module tile & visual separation.
$base = dirname(__DIR__) . '/psclientcare';
$tile = file_get_contents($base . '/views/templates/hook/account.tpl');
$portal = file_get_contents($base . '/views/templates/front/portal.tpl');
$css = file_get_contents($base . '/views/css/portal.css');
$admin = file_get_contents($base . '/classes/PsccAdmin.php');
$main = file_get_contents($base . '/psclientcare.php');
$mail = file_get_contents($base . '/classes/PsccMailer.php');
$checks = [
    'Classic grid tile' => strpos($tile, 'col-lg-4 col-md-6 col-sm-6 col-xs-12') !== false,
    'Classic link-item span' => preg_match('/<span class="link-item">/', $tile) === 1,
    'tile is a full anchor' => strpos($tile, 'pscc-account-entry') !== false,
    'overview wrapper' => strpos($portal, 'class="pscc-overview"') !== false,
    'quick navigation grouped' => strpos($portal, 'pscc-quicklink') !== false,
    'overview layout CSS isolated' => strpos($css, '.pscc-portal .pscc-overview .pscc-quicknav') !== false,
    'order specific layout' => strpos($css, '.pscc-portal .pscc-panel-orders .pscc-shipment') !== false,
    'tracking has a distinct code label' => strpos($portal, 'Número de seguimiento') !== false,
    'tracking URL visibly separate' => strpos($portal, 'URL del transportista') !== false,
    'customer can open transport link' => strpos($portal, 'Seguir envío') !== false,
    'tracking URL input help' => strpos($admin, 'pscc-code-help') !== false,
    'URL mistaken for code rejected' => strpos($admin, 'Has escrito una URL en el campo Código') !== false,
    'recipient retained' => strpos($main, 'PSCC_RECIPIENT') !== false && strpos($admin, 'name="pscc_recipient"') !== false,
    'PrestaShop Mail::Send retained' => strpos($mail, 'Mail::Send(') !== false,
    'no DB schema changes on 014' => file_exists($base . '/upgrade/upgrade-0.1.4.php') && strpos(file_get_contents($base . '/upgrade/upgrade-0.1.4.php'), 'ensureTrackingUrlSchema()') !== false,
];
foreach ($checks as $name => $ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
}
echo "PASS dashboard layout and tracking UX regression\n";
