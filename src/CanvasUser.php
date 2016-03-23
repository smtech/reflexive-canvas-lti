<?php
	
namespace smtech\ReflexiveCanvasLTI;

class CanvasUser extends \LTI_User {
	
	/* @var CanvasProperties $canvas */
	public $canvas;
	
	public function __construct($lti_user) {
		if ($lti_user instanceof \LTI_User) {
			
			/* copy existing properties */
	        foreach (get_object_vars($lti_user) as $key => $value) {
	            $this->$key = $value;
	        }
	        
			$this->$canvas = new CanvasSettings($lti_user->getResourceLink()->settings);
		} else {
			throw new CanvasUser_Exception(
				'Expected an instance of `LTI_User`, received `' . get_class($lti_user) . '` instead.',
				CanvasUser_Exception::MISSING_PARAMETER
			);
		}
	}
	
	public function getRole() {
		if ($this->isAdmin()) {
			return ReflexiveCanvasLTI::ROLE_ADMIN;
		} elseif ($this->isStaff()) {
			return ReflexiveCanvasLTI::ROLE_STAFF;
		} elseif ($this->isLearner()) {
			return ReflexiveCanvasLTI::ROLE_LEARNER;
		}
	}
}