<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
/** Safe, additive upgrade. Existing tickets, messages and email settings remain intact. */
function upgrade_module_0_1_5($module)
{
    return PsccSubmission::installSchema();
}
