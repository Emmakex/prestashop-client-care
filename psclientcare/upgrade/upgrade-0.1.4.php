<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
/** Visual-only patch: retain all tickets, recipient configuration and tracking. */
function upgrade_module_0_1_4($module)
{
    return $module->ensureTrackingUrlSchema();
}
