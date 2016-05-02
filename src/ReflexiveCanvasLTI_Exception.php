<?php
	
namespace smtech\ReflexiveCanvasLTI;

class ReflexiveCanvasLTI_Exception extends \Exception {
	const UNEXPECTED_TYPE = 1;
	const MISSING_PARAMETER = 2;
	const MISSING_API = 3;
	const MISSING_INFORMATION = 4;
	const UNKNOWN_REQUEST_TYPE = 5;
	const MYSQL = 6;
}