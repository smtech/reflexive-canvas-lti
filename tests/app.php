<?php
	
/* help ourselves to the Composer autoloader... */
require_once str_replace('/vendor/smtech/reflexive-canvas-lti', '', __DIR__) . '/../vendor/autoload.php';

$lti = new ResponsiveCanvasLTI(new mysqli)