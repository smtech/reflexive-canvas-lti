<?php

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\LTI\ToolProvider;

/* look up the currently authenticated Canvas user's profile via API request */
try {
    $profile = $toolbox->api_get('users/' . $_SESSION[ToolProvider::class]['canvas']['user_id'] . '/profile');
} catch (Exception $e) {
    $message = json_decode($e->getMessage(), true);
    require TEMPLATE . '/error.inc.php';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <title><?= $toolbox->config('TOOL_NAME') ?></title>
    </head>
    <body>
        <h1><?= $toolbox->config('TOOL_NAME') ?></h1>
        <pre><?= print_r($profile, true) ?></pre>
    </body>
</html>
