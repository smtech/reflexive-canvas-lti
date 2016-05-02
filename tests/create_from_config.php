<?php
	
/* help ourselves to the Composer autoloader... */
require_once str_replace('/vendor/smtech/reflexive-canvas-lti', '', __DIR__) . '/../vendor/autoload.php';

use smtech\ReflexiveCanvasLTI\ReflexiveCanvasLTI;

$lti = ReflexiveCanvasLTI::newInstanceFromConfig(__DIR__ . '/' . basename(__FILE__, '.php') . '.xml');

var_dump($lti);