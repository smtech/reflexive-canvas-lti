<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

/* start a fresh session to prevent accidental authentication */
session_start();
session_regenerate_id(true);

/* construct our Tool Provider object */
$_SESSION['lti'] = new ReflexiveCanvasLTI(__DIR__ . '/../config.xml');

/* authenticate the Tool Consumer and redirect to the appropriate handler URL */
$_SESSION['lti']->handle_request();
