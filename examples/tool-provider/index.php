<?php

require_once 'common.inc.php';

/* do we have an LTI request? */
if ($toolbox->isLaunching()) {
    require ACTION . '/launch.inc.php';
    exit;
}

/* otherwise, this must be some sort of administrative action */
$action = (empty($_REQUEST['action']) ? 'undefined' : strtolower($_REQUEST['action']));
switch ($action) {
    case 'reset':
        require ACTION . '/reset.inc.php';
        exit;

    case 'config':
    default:
        require ACTION . '/config.inc.php';
        exit;
}
