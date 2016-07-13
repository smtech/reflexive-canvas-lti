<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

$lti = new ReflexiveCanvasLTI(__DIR__ . '/../config.xml');
header('Content-type: application/xml');
echo $lti->generateConfigXml();
exit;
