<?php
	
namespace smtech\ReflexiveCanvasLTI;

use DOMDocument;
use DOMXPath;
use mysqli;
use ReflectionClass;

use Log;

use LTI_User;
use LTI_Tool_Provider;

use Battis\AppMetadata;
use Battis\ConfigXML;
use Battis\HierarchicalSimpleCache;

use smtech\CanvasPest\CanvasPest;

use Zend\Permissions\Rbac\Rbac;
use Zend\Permissions\Rbac\Role;

class ReflexiveCanvasLTI extends LTI_Tool_Provider {
	
	const REQUEST_BASE = 'base';
	const REQUEST_LAUNCH = 'launch';
	const REQUEST_DASHBOARD = 'dashboard';
	const REQUEST_CONTENT_ITEM = 'content-item';
	const REQUEST_CONFIGURE = 'configure';
	const REQUEST_ERROR = 'error';
		
	/** @var string[] $handlers */
	protected $handlers = array();
	
	/** @var AppMetadata $metadata */
	public $metadata = false;
	
	/** @var CanvasPest $api */
	public $api = false;
	
	/** @var mysqli $sql */
	public $sql = false;
	
	/** @var SimpleCache $cache */
	protected $cache = false;
	
	/** @var Log $log */
	protected $log = false;
	
	const ROLE_ADMIN = 'admin';
	const ROLE_STAFF = 'staff';
	const ROLE_LEARNER = 'learner';
	
	const PERMISSION_VIEW = 'view';
	const PERMISSION_EDIT = 'edit';
	const PERMISSION_CONFIGURE = 'configure';
	
	/** @var \Zend\Permissions\Rbac\Rbac $rbac */
	protected $rbac = false;
	
	/**
	 * Construct an LTI tool provider (TP) that provides an working environment
	 * for an LTI , (persistent app metadata, SQL database, role-based
	 * access control, etc.)
	 **/
	public function __construct($sql, $handlerUrl, $metadata = null, $api = null, $log = null, $callbackHandler = null) {

		$this->setSql($sql);

		parent::__construct(\LTI_Data_Connector::getDataConnector($this->sql), $callbackHandler);
		
		$this->setHandlerUrl($handlerUrl);
		$this->setMetadata($metadata);
		$this->setApi($api);
		$this->setLog($log);
		$this->defineRbac();

		$this->cache = new HierarchicalSimpleCache($this->sql, __CLASS__);
		$this->cache->purgeExpired();
	}
		
	public static function newInstanceFromConfig($configuration) {
		$config = new ConfigXML($configuration);
		$sql = $config->newInstanceOf(mysqli::class, 'mysql');
		if ($sql->connect_error !== null) {
			throw new ReflexiveCanvasLTI_Exception(
				"MySQL error {$sql->connect_errno}: {$sql->connect_error}",
				ReflexiveCanvasLTI_Exception::MYSQL
			);
		}
		
		return new ReflexiveCanvasLTI(
			$sql,
			$config->toArray('app/launch')[0],
			new AppMetadata($sql, $config->toArray('app/id')[0]),
			$config->newInstanceOf(CanvasPest::class, 'canvas'),
			($config->count('app/log') ? $config->toArray('app/log') : null)
		);
	}
	
	/**
	 * Define the Role-Based Access Control structure.
	 *
	 * This method is _meant_ to be over-ridden by child objects to define
	 * alternate, specific role and permission structures
	 *
	 * @see ReflexiveCanvasLTI::getCanvasRoles() Use getCanvasRoles() to get a list
	 *		of available roles defined in the Canvas instance
	 **/
	protected function defineRbac() {
		/* root of the Role-Based Access Control structure */
		$this->rbac = new Rbac();
		
		/*
		 * basic roles
		 *
		 * admin
		 *   |
		 * staff    learner
		 */
		$this->rbac->addRole(self::ROLE_ADMIN);
		$this->rbac->addRole(self::ROLE_STAFF, self::ROLE_ADMIN);
		$this->rbac->addRole(self::ROLE_LEARNER);
		
		/* basic permissions */
		$this->rbac->addPermission(self::PERMISSION_VIEW);
		$this->rbac->addPermission(self::PERMISSION_EDIT);
		$this->rbac->addPermission(self::PERMISSION_CONFIGURE);
		
		/* assign permissions to roles */
		$this->rbac->getRole(self::ROLE_ADMIN)->addPermission(self::PERMISSION_CONFIGURE);
		$this->rbac->getRole(self::ROLE_STAFF)->addPermission(self::PERMISSION_EDIT);
		$this->rbac->getRole(self::ROLE_LEARNER)->addPermission(self::PERMISSION_VIEW);
	}
	
	/**
	 * Check that this user has permission for a particular action
	 **/
	public function allows(LTI_User $user, $action) {
		return $this->rbac->isGranted($user->getRole(), $action);
	}
	
	/**
	 * Get a list of roles defined in the Canvas instance
	 **/
	public function getCanvasRoles() {
		if ($this->api) {
			/* determine from which account to request roles */
			$account_id = false;
			if (!empty($this->user->canvas['account_id'])) {
				$account_id = $this->user->canvas['account_id'];
			} elseif (!empty($this->user->canvas['course_id'])) {
				$course = $this->api->get('courses/' . $this->user->canvas['course_id']);
				$account_id = $course['account_id'];
			} else {
				throw new ReflexiveCanvasLTI_Exception(
					'Could not determine from which Canvas account roles should be requested.',
					ReflexiveCanvasLTI_Exception::MISSING_INFORMATION
				);
			}
			
			/* return requested roles, caching if possible */
			$roles = $this->cache->getCache(__FUNCTION__);
			if (empty($roles)) {
				$response = $api->get(
					"accounts/$account_id/roles",
					array(
						'show_inherited' => true
					)
				);
				$roles = array();
				foreach ($response as $role) {
					$roles[$role['id']] = $role;
				}
				$this->cache->setCache(__FUNCTION__, $roles);
			}
			return $roles;
		} else {
			throw new ReflexiveCanvasLTI_Exception(
				'Cannot access the Canvas API without a configured instance of `smtech\CanvasPest\CanvasPest.',
				ReflexiveCanvasLTI_Exception::MISSING_API
			);
		}
	}
	
	public function setMetadata(AppMetadata $metadata) {
		$this->metadata = $metadata;
	}
	
	public function setApi(CanvasPest $api) {
		$this->api = $api;
	}
	
	public function setSql(mysqli $sql) {
		$this->sql = $sql;
	}
	
	public function setLog($log) {		
		if (is_a($log, Log::class)) {
			$this->log = $log;
		} elseif (is_array($log)){
			$this->log = call_user_func_array(array('\Log::factory'), $log);
		} else {
			new ReflexiveCanvasLTI_Exception(
				'Either an instance of `Log` or an array of parameters for the Log::factory() static method were expected.',
				ReflexiveCanvasLTI_Exception::MISSING_PARAMETER
			);
		}
	}
	
	public function setHandlerUrl($requestType_or_list, $url = null) {
		
		/* figure out if we have a handler => URL pair, or an associative list of handler => URL pairs */
		$list = false;
		if (is_array($requestType_or_list)) {
			$list = $requestType_or_list;
		} elseif ($url && is_string($requestType_or_list)) {
			$list[$requestType_or_list] = $url;
		}
		
		/* walk through our list of pairs and assign them */
		if ($list) {
			foreach($list as $request => $url) {
				switch (strtolower($request)) {
					case self::REQUEST_BASE:
					case self::REQUEST_LAUNCH:
					case self::REQUEST_DASHBOARD:
					case self::REQUEST_CONTENT_ITEM:
					case self::REQUEST_CONFIGURE:
					case self::REQUEST_ERROR:
						$handlers[strtolower($request)] = $url;
						break;
					default:
						throw new ReflexiveCanvasLTI_Exception(
							'Unknown LTI request type "' . $request . '".',
							ReflexiveCanvasLTI_Exception::UNKNOWN_REQUEST_TYPE
						);
				}
			}
		} else {
			throw new ReflexiveCanvasLTI_Exception(
				'Expected an associative array of LTI request types and handler URLs or a single LTI request type and a handler URL.',
				ReflexiveCanvasLTI::MISSING_PARAMETER
			);
		}
		
		/* default the base handler URL to the first one given, if none specified */
		if (empty($handlers[self::REQUEST_BASE])) {
			$parts = explode('?', $list[0]);
			$handlers[self::REQUEST_BASE] = $parts[0];
		}
	}
	
	public function assignRedirect($requestType) {
		if (empty($handlers[$requestType])) {
			return $handlers[REQUEST_BASE] . "?lti-request=$requestType";
		} else {
			return $handlers[$requestType];
		}
	}
	
	public function handle_request() {
		parent::handle_request();
		$this->user = new CanvasUser($this->user);
	}
	
	public function onLaunch() {
		$this->redirectUrl = $this->assignRedirect(self::REQUEST_LAUNCH);
	}
	
	public function onDashboard() {
		$this->redirectUrl = $this->assignRedirect(self::REQUEST_DASHBOARD);
	}
	
	public function onConfigure() {
		$this->redirectUrl = $this->assignRedirect(self::REQUEST_CONFIGURE);
	}
	
	public function onContentItem() {
		$this->redirectUrl = $this->assignRedirect(self::REQUEST_CONTENT_ITEM);
	}
	
	public function onError() {
		$this->redirectUrl = $this->assignRedirect(self::REQUEST_ERROR);
	}
}