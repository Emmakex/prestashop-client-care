<?php
define('_PS_VERSION_', '9.0.0');
require __DIR__ . '/../psclientcare/classes/PsccRepository.php';
function ok($condition, $name) {if (!$condition) {throw new Exception('FAIL: '.$name);}}
ok(PsccRepository::isValidTrackingUrl(''), 'empty optional');
ok(PsccRepository::isValidTrackingUrl('https://www.seur.com/seguimiento?code=A123'), 'HTTPS URL');
ok(PsccRepository::isValidTrackingUrl('http://carrier.test/track/99'), 'HTTP URL');
ok(!PsccRepository::isValidTrackingUrl('javascript:alert(1)'), 'javascript blocked');
ok(!PsccRepository::isValidTrackingUrl('data:text/html,test'), 'data scheme blocked');
ok(!PsccRepository::isValidTrackingUrl('https://user:pass@carrier.test/path'), 'credentials blocked');
ok(!PsccRepository::isValidTrackingUrl("https://carrier.test/track\n"), 'whitespace blocked');
ok(!PsccRepository::isValidTrackingUrl('https://carrier.test/'.str_repeat('a', 2200)), 'oversized URL blocked');
print("PASS tracking URL regression\n");
