<?php

namespace smtech\ReflexiveCanvasLTI;

use mysqli;
use Serializable;

use Log;

use Battis\AppMetadata;
use Battis\ConfigXML;
use Battis\DataUtilities;

use smtech\CanvasPest\CanvasPest;
use smtech\ReflexiveCanvasLTI\LTI\ToolProvider;
use smtech\ReflexiveCanvasLTI\LTI\Configuration\Generator;
use smtech\ReflexiveCanvasLTI\LTI\Configuration\LaunchPrivacy;
use smtech\ReflexiveCanvasLTI\Exception\ConfigurationException;

class Toolbox implements Serializable {

	const
		DEFAULT_LAUNCH_PRIVACY = 'public',
		TOOL_METADATA_TABLE = 'tool_metadata';

	/** @var AppMetadata $metadata Persistent metadata storage */
	protected $metadata = false;

	/** @var CanvasPest $api Provide object-oriented interface to Canvas API */
	private $api = false;

	/** @var mysqli $mysql Connection to MySQL database */
	private $mysql = false;

	/** @var ToolProv $toolProvider LTI tool provider */
	private $toolProvider;

	/** @var Generator $generator LTI Configuration XML generator */
	private $generator;

	/** @var Log $logger Log file manager */
	private $logger = false;

	public function serialize() {
		return serialize([
			'config' => $this->metadata['TOOL_CONFIG_FILE']
		]);
	}

	public function unserialize($serialized) {
		$data = unserialize($serialized);
		$this->loadConfiguration($data['config']);
	}

	public static function fromConfiguration($configFilePath, $forceRecache = false) {
		return new Toolbox($configFilePath, $forceRecache);
	}

	private function __construct($configFilePath, $forceRecache = false) {
		$this->loadConfiguration($configFilePath, $forceRecache);
	}

	private function loadConfiguration($configFilePath, $forceRecache = false) {

		$logQueue = [];

		/* load the configuration file */
		$config = new ConfigXML($configFilePath);

		/* configure database connections */
		$this->setMySQL($config->newInstanceOf(mysqli::class, '/config/mysql'));

		/* configure metadata caching */
		$id = $config->toString('/config/tool/id');
		if (empty($id)) {
			$id = basename(dirname($configFilePath)) . '_' . md5(__DIR__ . file_get_contents($configFilePath));
			$logQueue[] = "    Automatically generated ID $id";
		}
		$this->setMetadata(new AppMetadata($this->mysql, $id, self::TOOL_METADATA_TABLE));

		/* update metadata */
		if (
			$forceRecache ||
			empty($this->metadata['TOOL_ID']) ||
			empty($this->metadata['TOOL_LAUNCH_URL']) ||
			empty($this->metadata['TOOL_CONFIG_FILE'])
		) {
			$tool = $config->toArray('/config/tool')[0];

			$this->metadata['TOOL_ID'] = $id;
			$this->metadata['TOOL_NAME'] = (empty($tool['name']) ? $id : $tool['name']);
			$this->metadata['TOOL_CONFIG_FILE'] = realpath($configFilePath);
			$configPath = dirname($this->metadata['TOOL_CONFIG_FILE']);

			if (!empty($tool['description'])) {
				$this->metadata['TOOL_DESCRIPTION'] = $tool['description'];
			} elseif (isset($this->metadata['TOOL_DESCRIPTION'])) {
				unset($this->metadata['TOOL_DESCRIPTION']);
			}

			if (!empty($tool['icon'])) {
				$this->metadata['TOOL_ICON_URL'] = (
					file_exists("$configPath/{$tool['icon']}") ?
						DataUtilities::URLfromPath("$configPath/{$tool['icon']}") :
						$tool[self::ICON]
				);
			} elseif (isset($this->metadata['TOOL_ICON_URL'])) {
				unset($this->metadata['TOOL_ICON_URL']);
			}

			$this->metadata['TOOL_LAUNCH_PRIVACY'] = (
				empty($tool['launch-privacy']) ?
					self::DEFAULT_LAUNCH_PRIVACY :
					$tool['launch-privacy']
			);

			if (!empty($tool['domain'])) {
				$this->metadata['TOOL_DOMAIN'] = $tool['domain'];
			} elseif (isset($this->metadata['TOOL_DOMAIN'])) {
				unset($this->metadata['TOOL_DOMAIN']);
			}

			$this->metadata['TOOL_LAUNCH_URL'] = (
				empty($tool['authenticate']) ?
					DataUtilities::URLfromPath($_SERVER['SCRIPT_FILENAME']) :
					DataUtilities::URLfromPath("$configPath/{$tool['authenticate']}")
			);

			$logQueue[] = "    Tool metadata configured";
		}
		$configPath = dirname($this->metadata['TOOL_CONFIG_FILE']);

		/* configure logging */
		if ($forceRecache || empty($this->metadata['TOOL_LOG'])) {
			$log = "$configPath/" . $config->toString('/config/tool/log');
			shell_exec("touch \"$log\"");
			$this->metadata['TOOL_LOG'] = realpath($log);
		}
		$this->setLog(Log::singleton('file', $this->metadata['TOOL_LOG']));
		if ($forceRecache) {
			$this->log("Resetting LTI configuration from $configFilePath");
		}
		if (!empty($logQueue)) {
			foreach ($logQueue as $message) {
				$this->log($message);
			}
			unset($logQueue);
		}

		/* configure tool provider */
		if ($forceRecache || empty($this->metadata['TOOL_HANDLER_URLS'])) {
			$handlers = $config->toArray('/config/tool/handlers')[0];
			if (empty($handlers) || !is_array($handlers)) {
				throw new ConfigurationException(
					'At least one handler/URL pair must be specified',
					ConfigurationException::TOOL_PROVIDER
				);
			}
			foreach ($handlers as $request => $path) {
				$handlers[$request] = DataUtilities::URLfromPath("$configPath/$path");
			}
			$this->metadata['TOOL_HANDLER_URLS'] = $handlers;
			$this->log('    Tool provider handler URLs configured');
		}

		/* configure API access */
		if ($forceRecache || empty($this->metadata['TOOL_CANVAS_API'])) {
			$this->metadata['TOOL_CANVAS_API'] = $config->toArray('/config/canvas')[0];
			if (empty($this->metadata['TOOL_CANVAS_API'])) {
				throw new ConfigurationException(
					'Canvas API credentials must be provided',
					ConfigurationException::CANVAS_API_MISSING
				);
			}
			$this->log('    Canvas API credentials configured');
		}
		$canvas = $this->metadata['TOOL_CANVAS_API'];
		/*
		 * IMPORTANT this must be the last action in the constructor so that the
		 * CANVAS_API_INCORRECT exception can be caught without compromising the
		 * integrity of the rest of the constructor!
		 */
		if (!empty($canvas['url']) && !empty($canvas['token'])) {
			$this->setAPI(new CanvasPest($canvas['url'], $canvas['token']));
		} else {
			throw new ConfigurationException(
				'Canvas URL and Token required',
				ConfigurationException::CANVAS_API_INCORRECT
			);
		}
	}

	public function setMetadata(AppMetadata $metadata) {
		$this->metadata = $metadata;
	}

	public function getMetadata() {
		return $this->metadata;
	}

	public function config($key, $value = null) {
		if ($value !== null) {
			$this->metadata[$key] = $value;
		} else {
			return $this->metadata[$key];
		}
	}

	public function setToolProvider(ToolProvider $toolProvider) {
		$this->toolProvider = $toolProvider;
	}

	public function getToolProvider() {
		if (empty($this->toolProvider)) {
			$this->setToolProvider(new ToolProvider(
				$this->mysql,
				$this->metadata['TOOL_HANDLER_URLS']
			));
		}
		return $this->toolProvider;
	}

	public function authenticate() {
		$this->getToolProvider()->execute();
	}

	public function isLaunching() {
		return !empty($_POST['lti_message_type']);
	}

	public function createConsumer($name, $key = false, $secret = false) {
		if ($this->getToolProvider()->createConsumer($name, $key, $secret)) {
			$this->log("Created consumer $name");
		} else {
			$this->log("Could not recreate consumer '$name', consumer already exists");
		}
	}

	public function getConsumers() {
		return $this->getToolProvider()->getConsumers();
	}

	public function setAPI(CanvasPest $api) {
		$this->api = $api;
	}

	public function getAPI() {
		return $this->api;
	}

	public function get($url, $data = [], $headers = []) {
		return $this->getAPI()->get($url, $data, $headers);
	}

	public function post($url, $data = [], $headers = []) {
		return $this->getAPI()->post($url, $data, $headers);
	}

	public function put($url, $data = [], $headers = []) {
		return $this->getAPI()->put($url, $data, $headers);
	}

	public function delete($url, $data = [], $headers = []) {
		return $this->getAPI()->delete($url, $data, $heaers);
	}

	public function setMySQL(mysqli $mysql) {
		$this->mysql = $mysql;
	}

	public function getMySQL() {
		return $this->sql;
	}

	public function query($query) {
		return $this->getMySQL()->query($query);
	}

	public function setLog(Log $log) {
		$this->logger = $log;
	}

	public function getLog() {
		return $this->logger;
	}

	/**
	 * Add a message to the tool log file
	 *
	 * @see https://pear.php.net/package/Log/docs/1.13.1/Log/Log_file.html#methodlog
	 *      Log_file::log() documentation from Pear
	 *
	 * @param string $message
	 * @param string $priority (Optional, defaults to `PEAR_LOG_INFO`)
	 * @return boolean Success
	 */
	public function log($message, $priority = null) {
		return $this->getLog()->log($message, $priority);
	}

	public function setGenerator(Generator $generator) {
		$this->generator = $generator;
	}

	public function getGenerator() {
		if (empty($this->generator)) {
			$this->generator = new Generator(
				$this->metadata['TOOL_NAME'],
				$this->metadata['TOOL_ID'],
				$this->metadata['TOOL_LAUNCH_URL'],
				(empty($this->metadata['TOOL_DESCRIPTION']) ? false : $this->metadata['TOOL_DESCRIPTION']),
				(empty($this->metadata['TOOL_ICON_URL']) ? false : $this->metadata['TOOL_ICON_URL']),
				(empty($this->metadata['TOOL_LAUNCH_PRIVACY']) ? LaunchPrivacy::USER_PROFILE() : $this->metadata['TOOL_LAUNCH_PRIVACY']),
				(empty($this->metadata['TOOL_DOMAIN']) ? false : $this->metadata['TOOL_DOMAIN'])
			);
		}
		return $this->generator;
	}

	public function saveConfigurationXML() {
		return $this->getGenerator()->saveXML();
	}
}
