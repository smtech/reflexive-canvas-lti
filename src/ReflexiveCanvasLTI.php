<?php

namespace smtech\ReflexiveCanvasLTI;

use DateTime;
use mysqli;
use DOMDocument;

use Log;

use LTI_Tool_Provider;
use LTI_Tool_Consumer;

use Battis\AppMetadata;
use Battis\ConfigXML;
use Battis\DataUtilities;

use smtech\CanvasPest\CanvasPest;

/**
 * Transparently (mostly) manage LTI Tool Provider authentication and app
 * environment management.
 *
 * @author Seth Battis <SethBattis@stmarksschool.org>
 */
class ReflexiveCanvasLTI extends LTI_Tool_Provider {

	const HASH_ALGORITHM = 'sha256';

	const DEFAULT_LAUNCH_PRIVACY = 'public';

	const REQUEST_BASE = 'base';
	const REQUEST_LAUNCH = 'launch';
	const REQUEST_DASHBOARD = 'dashboard';
	const REQUEST_CONTENT_ITEM = 'content-item';
	const REQUEST_CONFIGURE = 'configure';
	const REQUEST_ERROR = 'error';

	const TOOL_METADATA_TABLE = 'tool_metadata';

	const CONFIG_ROOT = '/config/';
	const KEY_PREFIX = 'TOOL_';
	const HANDLER_PREFIX = 'handler_';

	const TOOL = 'tool';
		const NAME = 'name';
		const ID = 'id';
		const DESCRIPTION = 'description';
		const ICON = 'icon';
		const LAUNCH_PRIVACY = 'privacy';
		const DOMAIN = 'domain';
		const HANDLER_URLS = 'handlers';
		const LAUNCH_URL = 'authenticate';
	const MYSQL = 'mysql';
	const CANVAS_API = 'canvas';
		const URL = 'url';
		const TOKEN = 'token';
	const LOG_PATH = 'log';


	/** @var AppMetadata $metadata Persistent metadata storage */
	public $metadata = false;

	/** @var CanvasPest $api Provide object-oriented interface to Canvas API */
	public $api = false;

	/** @var mysqli $sql Connection to MySQL database */
	public $sql = false;

	/** @var Log $logger Log file manager */
	protected $logger = false;

	/**
	 * Construct an LTI tool provider (TP) that provides an working environment
	 * for an LTI , (persistent app metadata, SQL database, role-based
	 * access control, etc.)
	 **/

	/**
	 * Construct an LTI Tool Provider with supporting objects to facilitate
	 * general tool-building (persistent app metadata, MySQL database, logging)
	 *
	 * @param string $configFilePath File-system path to a configuration XML file
	 * @param boolean $forceRecache (Optional, defaults to `false`) Whether or
	 *     not the rely on already-cached data or to force a re-read of a
	 *     (potentially updated) configuration file.
	 */
	public function __construct($configFilePath, $forceRecache = false) {

		$log = [];

		$config = new ConfigXML($configFilePath);
		$configPath = dirname($configFilePath);

		/* configure database connections */
		$this->setSql($config->newInstanceOf(\mysqli::class, self::CONFIG_ROOT . self::MYSQL));

		/* configure caching */
		$tool = $config->toArray(self::CONFIG_ROOT . self::TOOL)[0];
		$id = $tool[self::ID];
		if (empty($id)) {
			$id = basename($configPath) . '_' . md5(__DIR__ . file_get_contents($configFilePath));
			$log[] = "Automatically generated ID $id";
		}
		$this->setMetadata(new AppMetadata($this->sql, $id, self::TOOL_METADATA_TABLE));

		/* update metadata */
		if (
			$forceRecache ||
			empty($this->metadata[$this->getKey(self::ID)]) ||
			empty($this->metadata[$this->getKey(self::LAUNCH_URL)])
		) {
			$this->metadata[$this->getKey(self::ID)] = $id;

			$this->metadata[$this->getKey(self::NAME)] = (
				empty($tool[self::NAME]) ?
					$id :
					$tool[self::NAME]
			);

			if (!empty($tool[self::DESCRIPTION])) {
				$this->metadata[$this->getKey(self::DESCRIPTION)] = $tool[self::DESCRIPTION];
			} elseif (isset($this->metadata[$this->getKey(self::DESCRIPTION)])) {
				unset($this->metadata[$this->getKey(self::DESCRIPTION)]);
			}

			if (!empty($tool[self::ICON])) {
				$this->metadata[$this->getKey(self::ICON)] = (
					file_exists("$configPath/{$tool[self::ICON]}") ?
						DataUtilities::URLfromPath("$configPath/{$tool[self::ICON]}") :
						$tool[self::ICON]
				);
			} elseif (isset($this->metadata[$this->getKey(self::ICON)])) {
				unset($this->metadata[$this->getKey(self::ICON)]);
			}

			$this->metadata[$this->getKey(self::LAUNCH_PRIVACY)] = (
				empty($tool[self::LAUNCH_PRIVACY]) ?
					self::DEFAULT_LAUNCH_PRIVACY :
					$tool[self::LAUNCH_PRIVACY]
			);

			if (!empty($tool[self::DOMAIN])) {
				$this->metadata[$this->getKey(self::DOMAIN)] = $tool[self::DOMAIN];
			} elseif (isset($this->metadata[$this->getKey(self::DOMAIN)])) {
				unset($this->metadata[$this->getKey(self::DOMAIN)]);
			}

			$this->metadata[$this->getKey(self::LAUNCH_URL)] = (
				empty($tool[self::LAUNCH_URL]) ?
					DataUtilities::URLfromPath($_SERVER['SCRIPT_FILENAME']) :
					DataUtilities::URLfromPath("$configPath/{$tool[self::LAUNCH_URL]}")
			);

			$log[] = 'Tool metadata reconfigured';
		}

		/* configure logging */
		if ($forceRecache || empty($this->metadata[$this->getKey(self::LOG_PATH)])) {
			$this->metadata[$this->getKey(self::LOG_PATH)] = "$configPath/" . $config->toString(self::CONFIG_ROOT . self::TOOL . '/' .  self::LOG_PATH);
		}
		$this->setLog(Log::singleton('file', $this->metadata[$this->getKey(self::LOG_PATH)]));
		if ($forceRecache) {
			$this->log("Resetting LTI configuration from $configFilePath");
		}
		if (!empty($log)) {
			foreach ($log as $message) {
				$this->log($message);
			}
			unset($log);
		}

		/* configure handler URLs for differing request types */
		if ($forceRecache || empty($this->metadata[$this->getHandlerKey(self::REQUEST_BASE)])) {
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_BASE)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_BASE)]);
			}
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_LAUNCH)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_LAUNCH)]);
			}
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_CONTENT_ITEM)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_CONTENT_ITEM)]);
			}
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_DASHBOARD)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_DASHBOARD)]);
			}
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_CONFIGURE)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_CONFIGURE)]);
			}
			if (isset($this->metadata[$this->getHandlerKey(self::REQUEST_ERROR)])) {
				unset($this->metadata[$this->getHandlerKey(self::REQUEST_ERROR)]);
			}
			$handlerUrls = $config->toArray(self::CONFIG_ROOT . self::TOOL . '/' . self::HANDLER_URLS)[0];
			foreach($handlerUrls as $requestType => $url) {
				$handlerUrls[$requestType] = DataUtilities::URLfromPath("$configPath/$url");
			}
			$this->setHandlerUrl($handlerUrls);
			$this->log('Handler URLs reconfigured');
		}

		/* configure API access */
		if ($forceRecache || empty($this->metadata[$this->getKey(self::CANVAS_API)])) {
			$this->metadata[$this->getKey(self::CANVAS_API)] = $config->toArray(self::CONFIG_ROOT . self::CANVAS_API)[0];
			$this->log('Canvas API credentials reconfigured');
		}
		$this->setApi(new CanvasPest(
			$this->metadata[$this->getKey(self::CANVAS_API)][self::URL],
			$this->metadata[$this->getKey(self::CANVAS_API)][self::TOKEN]
		));

		/* load any missing MySQL tables for LTI_Tool_Provider */
		if (!$this->isLtiSchemaReady()) {
			$this->importLtiSchema();
			$this->log('LTI_Tool_Provider schema loaded into database');
		}
	}

	/**
	 * Test if the MySQL database already contains needed tables to support LTI
	 * Tool Provider operations.
	 *
	 * @return boolean
	 */
	protected function isLtiSchemaReady() {
		/* TODO is there a _better_ way of validating this? */
		$response = $this->sql->query("SHOW TABLES LIKE 'lti_%'");
		return $response->num_rows >= 5;
	}

	/**
	 * Import the `LTI_Tool_Provider` schema into the MySQL database.
	 *
	 * @return void
	 */
	protected function importLtiSchema() {
		$this->log('LTI_Tool_Provider tables not present, importing from schema');

		/* help ourselves to the Composer autoloader... */
		/* FIXME I have to imagine that assuming the install directory is 'vendor' is unsafe */
		if (strpos(__DIR__, '/vendor/')) {
		    $composer = require preg_replace('%(.*/vendor)/.*%', '$1/autoload.php', __DIR__);
		} else {
		    $composer = require __DIR__ . '/../vendor/autoload.php';
		}

		/* ...so that we can find the LTI_Tool_Provider database schema (oy!) */
		foreach (explode(';', file_get_contents(dirname($composer->findFile(LTI_Tool_Provider::class)) . '/lti-tables-mysql.sql')) as $query) {
			if (!empty(trim($query))) {
				if ($this->sql->query($query) === false) {
					$this->log("Loading LTI_Tool_Provider MySQL tables: {$this->sql->error}");
				}
			}
		}
	}

	/**
	 * Create a new tool consumer, optionally with specific key and shared
	 * secret.
	 *
	 * @param string $name Human-readable Tool Consumer name (e.g. "My School
	 *     Canvas")
	 * @param string $key (Optional) A unique key to identify this consumer
	 *     within the database
	 * @param string $secret (Optional) A shared secret to authenticate this tool
	 *     consumer
	 * @return void
	 */
	public function createConsumer($name, $key = false, $secret = false) {
		/* scan through existing consumers to be sure we're not creating a duplicate */
		$consumers = $this->getConsumers();
		$consumerExists = false;
		foreach ($consumers as $consumer) {
			if ($consumer->name == $name) {
				$consumerExists = true;
				break;
			}
		}

		if (!$consumerExists) {
			$consumer = new LTI_Tool_Consumer(
				($key ? $key : hash(self::HASH_ALGORITHM, 'key' . time())),
				$this->data_connector,
				true
			);
			$consumer->name = $name;
			$consumer->secret = ($secret ? $secret : hash(self::HASH_ALGORITHM, 'secret' . time()));
			$consumer->save();
			$this->log("Created tool consumer named \"$name\"");
		}

	}

	/**
	 * Generate a simple XML configuration for use when installing this app in a
	 * Tool Consumer.
	 *
	 * @see https://www.edu-apps.org/build_xml.html Edu App XML Config Builder
	 *      for a more complete `config.xml`
	 *
	 * @return string XML configuration for LTI placement in Tool Consumer
	 */
	public function generateConfigXml() {
		$lticc = 'http://www.imsglobal.org/xsd/imslticc_v1p0';
		$xmlns = 'http://www.w3.org/2000/xmlns/';
		$blti = 'http://www.imsglobal.org/xsd/imsbasiclti_v1p0';
		$lticm = 'http://www.imsglobal.org/xsd/imslticm_v1p0';
		$lticp = 'http://www.imsglobal.org/xsd/imslticp_v1p0';
		$xsi = 'http://www.w3.org/2001/XMLSchema-instance';

		$config = new DOMDocument('1.0', 'UTF-8');
		$config->formatOutput = true;

		$cartridge = $config->createElementNS($lticc, 'cartridge_basiclti_link');
		$config->appendChild($cartridge);
		$cartridge->setAttributeNS($xmlns, 'xmlns:blti', $blti);
		$cartridge->setAttributeNS($xmlns, 'xmlns:lticm', $lticm);
		$cartridge->setAttributeNS($xmlns, 'xmlns:lticp', $lticp);
		$cartridge->setAttributeNS($xmlns, 'xmlns:xsi', $xsi);
		$cartridge->setAttributeNS(
			$xsi,
			'xsi:schemaLocation',
			"$lticc $lticc.xsd $blti $blti.xsd $lticm $lticm.xsd $lticp $lticp.xsd"
		);

		$cartridge->appendChild($config->createElementNS(
			$blti,
			'blti:title',
			$this->metadata[$this->getKey(self::NAME)]
		));

		if (!empty($this->metadata[$this->getKey(self::DESCRIPTION)])) {
			$cartridge->appendChild($config->createElementNS(
				$blti,
				'blti:description',
				$this->metadata[$this->getKey(self::DESCRIPTION)]
			));
		}

		if (!empty($this->metadata[$this->getKey(self::ICON)])) {
			$cartridge->appendChild($config->createElementNS(
				$blti,
				'blti:icon',
				$this->metadata[$this->getKey(self::ICON)]
			));
		}

		$cartridge->appendChild($config->createElementNS(
			$blti,
			'blti:launch_url',
			$this->metadata[$this->getKey(self::LAUNCH_URL)]
		));

		$extensions = $config->createElementNS($blti, 'blti:extensions');
		$cartridge->appendChild($extensions);
		$extensions->setAttribute('platform', 'canvas.instructure.com');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			$this->metadata[$this->getKey(self::ID)]
		);
		$extensions->appendChild($property);
		$property->setAttribute('name', 'tool_id');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			$this->metadata[$this->getKey(self::LAUNCH_PRIVACY)]
		);
		$extensions->appendChild($property);
		$property->setAttribute('name', 'privacy_level');

		if (!empty($this->metadata[$this->getKey(self::DOMAIN)])) {
			$property = $config->createElementNS(
				$lticm,
				'lticm:property',
				$this->metadata[$this->getKey(self::DOMAIN)]
			);
			$extensions->appendChild($property);
			$property->setAttribute('name', 'domain');
		}

		$options = $config->createElementNS($lticm, 'lticm:options');
		$extensions->appendChild($options);
		$options->setAttribute('name', 'course_navigation');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			$this->metadata[$this->getKey(self::LAUNCH_URL)]
		);
		$options->appendChild($property);
		$property->setAttribute('name', 'url');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			$this->metadata[$this->getKey(self::NAME)]
		);
		$options->appendChild($property);
		$property->setAttribute('name', 'text');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			'public'
		);
		$options->appendChild($property);
		$property->setAttribute('name', 'visibility');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			'default'
		);
		$options->appendChild($property);
		$property->setAttribute('name', 'enabled');

		$property = $config->createElementNS(
			$lticm,
			'lticm:property',
			'true'
		);
		$options->appendChild($property);
		$property->setAttribute('name', 'enabled');

		$bundle = $config->createElement('cartridge_bundle');
		$cartridge->appendChild($bundle);
		$bundle->setAttribute('identiferref', 'BLT001_Bundle');

		$icon = $config->createElement('cartridge_icon');
		$cartridge->appendChild($icon);
		$icon->setAttribute('identifierref', 'BLT001_Icon');

		return $config->saveXML();
	}

	/**
	 * Generate an appropriately prefixed key to look up Tool-specific values in
	 * the persistent metadata.
	 *
	 * While the user can store anything that would be convenient in the
	 * metadata, this object stores all of _its_ metadata prefixed with a
	 * consistent string ("TOOL_" by default).
	 *
	 * @param string $key Data key to look up
	 * @return string Valid metadata key
	 */
	public function getKey($key) {
		return self::KEY_PREFIX . (string) $key;
	}

	/**
	 * Get the metadata key for a the URL of a particular request type handler
	 *
	 * @param string $requestType Refer to the `ReflexiveCanvasLTI::REQUEST_*`
	 *     constants for valid request types.
	 * @return string
	 */
	private function getHandlerKey($requestType) {
		return $this->getKey(self::HANDLER_PREFIX . strtolower($requestType));
	}

	/**
	 * Replace the existing tool metadata cache with an alternate metadata store
	 *
	 * Presumably it is obvious that doing this mid-operation is, most likely,
	 * a very real recipe for disaster?
	 *
	 * @param AppMetadata $metadata
	 */
	public function setMetadata(AppMetadata $metadata) {
		$this->metadata = $metadata;
	}

	/**
	 * Replace the existing CanvasPest object with an alternate
	 *
	 * @param CanvasPest $api Object to manage interaction with the Canvas API
	 */
	public function setApi(CanvasPest $api) {
		$this->api = $api;
	}

	/**
	 * Replace the existing MySQL object with an alternate
	 *
	 * Presumably it is obvious that swapping this out mid-operation is probably
	 * a bad idea.
	 *
	 * @param mysqli $sql
	 */
	public function setSql(mysqli $sql) {
		$this->sql = $sql;
		parent::__construct(\LTI_Data_Connector::getDataConnector($this->sql), null);
	}

	/**
	 * Replace the existing Log object with an alternate
	 *
	 * @param Log $log
	 */
	public function setLog(Log $log) {
		$this->logger = $log;
	}

	/**
	 * Update request-type/script-handler pairings
	 *
	 * This anticipates either a pair of strings or an associative array of pairs
	 *
	 * ```PHP
	 * ['requestType' => 'URL of script handling this request']
	 * ```
	 *
	 * @param string|string[] $requestType_or_list
	 * @param string $url Ignored if `$requestType_or_list` is an array,
	 *     required if `$requestType_or_list` is a string
	 */
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
						$this->metadata[$this->getHandlerKey($request)] = $url;
						break;
					default:
						throw new ReflexiveCanvasLTI_Exception(
							'Unknown LTI request type "' . $request . '".',
							ReflexiveCanvasLTI_Exception::UNKNOWN_REQUEST_TYPE
						);
				}
			}

			/* default the base handler URL to the first one given, if none specified */
			if (empty($this->metadata[$this->getHandlerKey(self::REQUEST_BASE)])) {
				reset($list);
				$this->metadata[$this->getHandlerKey(self::REQUEST_BASE)] = strtok(current($list), '?');
			}
		} else {
			throw new ReflexiveCanvasLTI_Exception(
				'Expected an associative array of LTI request types and handler URLs or a single LTI request type and a handler URL.',
				ReflexiveCanvasLTI_Exception::MISSING_PARAMETER
			);
		}
	}

	/**
	 * Add a message to the tool log file
	 *
	 * @see https://pear.php.net/package/Log/docs/1.13.1/Log/Log_file.html#methodlog Log_file::log() documentation from Pear
	 *
	 * @param string $message
	 * @param string $priority (Optional, defaults to `PEAR_LOG_INFO`)
	 * @return boolean Success
	 */
	public function log($message, $priority = null) {
		return $this->logger->log($message, $priority);
	}

	/**
	 * Assign a handler script based on request type
	 *
	 * @param  string $requestType
	 * @return string URL of the script that will handle the request
	 */
	public function assignRedirect($requestType) {
		if (empty($this->metadata[$this->getHandlerKey($requestType)])) {
			return $this->metadata[$this->getHandlerKey(self::REQUEST_BASE)] . "?lti-request=$requestType";
		} else {
			return $this->metadata[$this->getHandlerKey($requestType)];
		}
	}

	/**
	 * Check that the authentication process is complete (and successful)
	 *
	 * @return boolean
	 */
	public function isAuthenticated() {
		return $this->isOK && is_a($this->user, CanvasUser::class);
	}

	/**
	 * Post-authentication of tool consumer, before hand-off to handler script
	 *
	 * In this object, all method does is preprocess the `$user` instance
	 * variable into a somewhat more friendly `CanvasUser` object.
	 *
	 * Classes that extend `ReflexiveCanvasLTI` might consider implementing
	 * role-based authentication or per-placement differentiation of
	 * authentication by overriding this method.
	 *
	 * @param string $requestType
	 * @return void
	 */
	protected function callbackPreamble($requestType) {
		$this->user = new CanvasUser($this->user);
	}

	/**
	 * Handle redirection of launch requests to the handler script
	 * @return void
	 */
	public function onLaunch() {
		$this->callbackPreamble(self::REQUEST_LAUNCH);
		$this->redirectURL = $this->assignRedirect(self::REQUEST_LAUNCH);
	}

	/**
	 * Handle redirection of dashboard requests the handler script
	 * @return void
	 */
	public function onDashboard() {
		$this->callbackPreamble(self::REQUEST_DASHBOARD);
		$this->redirectURL = $this->assignRedirect(self::REQUEST_DASHBOARD);
	}

	/**
	 * Handle redirection of configure requests to the handler script
	 * @return void
	 */
	public function onConfigure() {
		$this->callbackPreamble(self::REQUEST_CONFIGURE);
		$this->redirectURL = $this->assignRedirect(self::REQUEST_CONFIGURE);
	}

	/**
	 * Handle redirection of content-item requests to the handler script
	 * @return void
	 */
	public function onContentItem() {
		$this->callbackPreamble(self::REQUEST_CONTENT_ITEM);
		$this->redirectURL = $this->assignRedirect(self::REQUEST_CONTENT_ITEM);
	}

	/**
	 * Handle redirection of error requests to the handler script
	 * @return void
	 */
	public function onError() {
		$this->callbackPreamble(self::REQUEST_ERROR);
		$this->redirectURL = $this->assignRedirect(self::REQUEST_ERROR);
	}
}
