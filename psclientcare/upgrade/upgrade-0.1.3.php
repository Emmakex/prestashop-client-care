<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
/** No destructive changes in v0.1.3; ensure the 0.1.2 tracking URL column exists. */
function upgrade_module_0_1_3($module)
{
    return $module->ensureTrackingUrlSchema();
}
