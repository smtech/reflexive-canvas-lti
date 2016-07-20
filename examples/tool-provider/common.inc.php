<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use smtech\ReflexiveCanvasLTI\Toolbox;

define('CONFIG_FILE', __DIR__ . '/config.xml');

/* start a session (if one isn't already active) */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* have we already got a cached toolbox? */
if (empty($_SESSION['toolbox'])) {
    $_SESSION['toolbox'] = Toolbox::fromConfiguration(CONFIG_FILE);
}
$toolbox =& $_SESSION['toolbox'];
