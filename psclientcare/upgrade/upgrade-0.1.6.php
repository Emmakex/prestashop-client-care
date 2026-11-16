<?php
if (!defined('_PS_VERSION_')) { exit; }
/** No destructive migration: all archived and existing records stay intact. */
function upgrade_module_0_1_6($module)
{
    return PsccSubmission::installSchema();
}
