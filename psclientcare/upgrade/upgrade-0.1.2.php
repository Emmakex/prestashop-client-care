<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/** Database migration: tracking URLs, preserving all existing tracking numbers and tickets. */
function upgrade_module_0_1_2($module)
{
    return $module->ensureTrackingUrlSchema();
}
