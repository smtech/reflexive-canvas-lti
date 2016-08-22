<?php

require_once __DIR__ . '/vendor/autoload.php';

use smtech\ReflexiveCanvasLTI\Toolbox;

define('CONFIG_FILE', __DIR__ . '/config.xml');
define('ACTION', __DIR__ . '/actions');
define('TEMPLATE', __DIR__ . '/templates');

@session_start(); // TODO I don't feel good about suppressing warnings

/* prepare the toolbox */
if (empty($_SESSION[Toolbox::class])) {
    $_SESSION[Toolbox::class] =& Toolbox::fromConfiguration(CONFIG_FILE);
}
$toolbox =& $_SESSION[Toolbox::class];
