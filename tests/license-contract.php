<?php
// Protect copyright and license packaging; independent of the PrestaShop runtime.
$base = dirname(__DIR__);
$main = file_get_contents($base . '/LICENSE');
$packaged = file_get_contents($base . '/psclientcare/LICENSE');
$module = file_get_contents($base . '/psclientcare/psclientcare.php');
$metadata = file_get_contents($base . '/psclientcare/config.xml');
$checks = array(
    'repository license exists' => $main !== false,
    'installable module includes same license' => $main === $packaged,
    'copyright' => strpos($main, '2026 Eduardo Yauri') !== false,
    'rights reserved' => strpos($main, 'Todos los derechos reservados') !== false,
    'package license header' => strpos($module, 'License: Proprietary') !== false,
    'no leftover MIT claim' => strpos($module, 'License: MIT') === false,
    'module author' => strpos($module, "\$this->author = 'Eduardo Yauri';") !== false,
    'author website' => strpos($metadata, 'https://www.eduardoyauriluna.com') !== false,
);
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAILED: $label\n");
        exit(1);
    }
}
echo "PASS license and copyright contract\n";
