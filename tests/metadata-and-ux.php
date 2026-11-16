<?php
$root = dirname(__DIR__) . '/psclientcare';
$main = file_get_contents($root . '/psclientcare.php');
$xml = file_get_contents($root . '/config.xml');
$tpl = file_get_contents($root . '/views/templates/front/portal.tpl');
$css = file_get_contents($root . '/views/css/portal.css');
$admin = file_get_contents($root . '/classes/PsccAdmin.php');
$mail = file_get_contents($root . '/classes/PsccMailer.php');
$checks = array(
  'version' => strpos($main, "\$this->version = '0.1.4'") !== false,
  'author' => strpos($main, "\$this->author = 'Eduardo Yauri'") !== false && strpos($xml, 'Eduardo Yauri') !== false,
  'author website' => strpos($main, 'https://www.eduardoyauriluna.com') !== false && strpos($admin, 'https://www.eduardoyauriluna.com') !== false,
  'tracking URL visible' => substr_count($tpl, "tracking_url|escape:'htmlall':'UTF-8'") >= 6,
  'URL text not only button' => strpos($tpl, 'pscc-tracking-address') !== false,
  'CSRF still present' => strpos($admin, 'validCsrf(') !== false,
  'recipient setting preserved' => strpos($admin, 'name="pscc_recipient"') !== false && strpos($main, 'PSCC_RECIPIENT') !== false,
  'PrestaShop native Mail::Send' => strpos($mail, 'Mail::Send(') !== false,
  'focus indication' => strpos($css, ':focus-visible') !== false,
  'reduced motion' => strpos($css, 'prefers-reduced-motion') !== false,
  'new tab safety' => strpos($tpl, 'rel="noopener noreferrer"') !== false,
  'responsive' => strpos($css, 'max-width:640px') !== false,
  'schema upgrade' => file_exists($root . '/upgrade/upgrade-0.1.4.php'),
);
foreach ($checks as $name => $pass) {
  if (!$pass) {fwrite(STDERR, "FAIL: $name\n"); exit(1);}
}
echo "PASS metadata and UX regression\n";
