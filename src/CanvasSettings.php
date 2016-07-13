<?php

namespace smtech\ReflexiveCanvasLTI;

/**
 * Wrapper to format Canvas-specific user settings nicely
 *
 * @author Seth Battis <SethBattis@stmarksschool.org>
 *
 * @see CanvasUser CanvasUser
 */
class CanvasSettings {

	const CANVAS_PREFIX = 'custom_canvas_';

	/**
	 * Construct object from the settings parameters sent by the Tool Consumer
	 * @param array $settings
	 */
	public function __construct($settings) {
		if (is_array($settings)) {

			/* selectively copy over Canvas settings */
			foreach($settings as $key => $value) {
				if(strpos($key, self::CANVAS_PREFIX) === 0) {
					$_key = str_replace(self::CANVAS_PREFIX, '', $key);
					$this->$_key = $value;
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
