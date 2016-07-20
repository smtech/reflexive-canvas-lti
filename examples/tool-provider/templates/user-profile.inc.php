<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

try {
    $profile = $toolbox->get("users/{$_SESSION['user']['canvas']['user_id']}/profile");
} catch (Exception $e) {
    $message = $e->getMessage();
    require 'error.inc.php';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <title><?= $toolbox->config('TOOL_NAME') ?></title>
    </head>
    <body>
        <h1><?= $toolbox->config('TOOL_NAME') ?></h1>
        <pre><?= print_r($profile) ?></pre>
    </body>
</html>
