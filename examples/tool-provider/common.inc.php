<?php

require_once __DIR__ . '/vendor/autoload.php';

use smtech\ReflexiveCanvasLTI\Toolbox;

define('CONFIG_FILE', __DIR__ . '/config.xml');
define('ACTION', __DIR__ . '/actions');
define('TEMPLATE', __DIR__ . '/templates');

/* start a session (if one isn't already active) */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* construct a toolbox if we don't already have one */
if (empty($_SESSION['toolbox'])) {
    $_SESSION['toolbox'] = Toolbox::fromConfiguration(CONFIG_FILE);
}
$toolbox =& $_SESSION['toolbox'];
