<?php

require_once 'common.inc.php';

header('Content-type: application/xml');
echo $toolbox->saveConfigurationXML();
exit;
