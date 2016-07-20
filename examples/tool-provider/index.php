<?php

require_once 'common.inc.php';

/* do we have an LTI request? */
if (!empty($_REQUEST['lti_message_type'])) {
    require 'templates/launch.inc.php';
    exit;
}

/* otherwise, this must be some sort of administrative action */
$action = (empty($_REQUEST['action']) ? 'undefined' : strtolower($_REQUEST['action']));
switch ($action) {
    case 'reset':
        require 'templates/reset.inc.php';
        exit;

    case 'config':
    default:
        require 'templates/config.inc.php';
        exit;
}
