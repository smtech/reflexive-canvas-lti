<?php
	
namespace smtech\ReflexiveCanvasLTI;

class CanvasSettings {
	
	const CANVAS_PREFIX = 'custom_canvas_';
	
	public function __construct($settings) {
		if (is_array($settings)) {
			
			/* selectively copy over Canvas settings */
			foreach($settings as $key => $value) {
				if(strpos($key, self::CANVAS_PREFIX) === 0) {
					$_key = str_replace(self::CANVAS_PREFIX, '', $key);
					$this->$key = $value;
				}
			}
			
		} else {
			throw new CanvasSettings_Exception(
				'Expected an associative array of settings passed to the LTI, received `' . get_class($settings) . '` instead.',
				CanvasSettings_Exception::MISSING_PARAMETER
			);
		}
	}
}