<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

session_start();
$lti =& $_SESSION['lti'];

try {
    $profile = $lti->api->get("users/{$lti->user->canvas->user_id}/profile");
} catch (Exception $e) {
    $message = $e->getMessage();
    require 'error.inc.php';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <title><?= $lti->metadata[$lti->getKey(ReflexiveCanvasLTI::NAME)] ?></title>
    </head>
    <body>
        <h1><?= $lti->metadata[$lti->getKey(ReflexiveCanvasLTI::NAME)] ?></h1>
        <pre><?= print_r($profile) ?></pre>
    </body>
</html>
