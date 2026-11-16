<?php
// Regression for the module's own Back Office CSRF protection.
define('_PS_VERSION_', '9.0.0');
define('_COOKIE_KEY_', 'test-only-fixed-secret');
require __DIR__ . '/../psclientcare/classes/PsccAdmin.php';

function psccCheck($valid, $message)
{
    if (!$valid) {
        fwrite(STDERR, 'FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

$generate = new ReflectionMethod('PsccAdmin', 'csrfToken');
$verify = new ReflectionMethod('PsccAdmin', 'validCsrf');

$token = $generate->invoke(null, 52, 1);
psccCheck(strlen($token) === 64, 'Expected HMAC-SHA256 token');
psccCheck($verify->invoke(null, $token, 52, 1), 'Legitimate form token');
psccCheck(!$verify->invoke(null, 'bad-token', 52, 1), 'Reject tampering');
psccCheck(!$verify->invoke(null, $token, 53, 1), 'Reject different employee');
psccCheck(!$verify->invoke(null, $token, 52, 2), 'Reject different shop');
psccCheck(!$verify->invoke(null, $token, 0, 1), 'Reject unauthenticated employee');
$previousDay = $generate->invoke(null, 52, 1, 1);
psccCheck($verify->invoke(null, $previousDay, 52, 1), 'Accept previous-day form');
$twoDaysAgo = $generate->invoke(null, 52, 1, 2);
psccCheck(!$verify->invoke(null, $twoDaysAgo, 52, 1), 'Reject expired form');

echo "PS Client Care admin CSRF regression: PASS\n";
