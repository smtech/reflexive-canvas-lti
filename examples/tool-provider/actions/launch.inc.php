<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

/* start a fresh session to prevent accidental authentication */
$toolbox->resetSession();

/* authenticate the Tool Consumer and redirect to the appropriate handler URL */
$toolbox->lti_authenticate();
