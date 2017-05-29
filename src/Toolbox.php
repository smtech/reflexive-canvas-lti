<?php
/** Toolbox class */

namespace smtech\ReflexiveCanvasLTI;

use mysqli;
use Serializable;

use Log;

use Battis\AppMetadata;
use Battis\ConfigXML;
use Battis\DataUtilities;

use smtech\CanvasPest\CanvasPest;
use smtech\ReflexiveCanvasLTI\LTI\ToolProvider;
use smtech\ReflexiveCanvasLTI\Exception\ConfigurationException;
use smtech\LTI\Configuration\Generator;
use smtech\LTI\Configuration\LaunchPrivacy;
use smtech\LTI\Configuration\Exception\ConfigurationException as LTIConfigGeneratorException;

/**
 * A toolbox of tools for quickly constructing LTI tool providers that hook
 * back into the Canvas API reflexively.
 *
 * The basic idea is that you need an XML configuration file of credentials and
 * use that to instantiate the Toolbox. The toolbox can then perform LTI
 * authentication, handle API requests, generate an LTI Configuration XML file,
 * etc. for you.
 *
 * @author Seth Battis
 * @version v1.0
 */
class Toolbox implements Serializable
{
    /** Default level of information-sharing privacy between consumer and provider */
    const DEFAULT_LAUNCH_PRIVACY = 'public';

    /** Name of the database table backing the tool metadata */
    const TOOL_METADATA_TABLE = 'tool_metadata';

    /** The path to the configuration file from which this toolbox was generated */
    const TOOL_CONFIG_FILE = 'TOOL_CONFIG_FILE';

    /**
     * The (ideally globally unique) identifier for the LTI tool provider
     */
    const TOOL_ID = 'TOOL_ID';

    /** The human-readable name of the tool */
    const TOOL_NAME = 'TOOL_NAME';

    /** The human-readable description of the tool */
    const TOOL_DESCRIPTION = 'TOOL_DESCRIPTION';

    /** The URL of the tool's icon image (if present) */
    const TOOL_ICON_URL = 'TOOL_ICON_URL';

    /** The domain from which Tool Consumer requests may emanate for the tool */
    const TOOL_DOMAIN = 'TOOL_DOMAIN';

    /** The URL of the script that will handle LTI authentication */
    const TOOL_LAUNCH_URL = 'TOOL_LAUNCH_URL';

    /** The level of information sharing between the LMS and the tool */
    const TOOL_LAUNCH_PRIVACY = 'TOOL_LAUNCH_PRIVACY';

    /** The path to the tool's log file */
    const TOOL_LOG = 'TOOL_LOG';

    /** An associative array of LTI request types and the URL that handles that request. */
    const TOOL_HANDLER_URLS = 'TOOL_HANDLER_URLS';

    /** An associative array of Canvas API credentials (`url` and `token`) */
    const TOOL_CANVAS_API = 'TOOL_CANVAS_API';

    /**
     * Persistent metadata storage
     * @var AppMetadata
     * @see Toolbox::config() Toolbox::config()
     */
    protected $metadata = false;

    /**
     * Object-oriented access to the Canvas API
     * @var CanvasPest
     */
    protected $api = false;

    /**
     * MySQL database connection
     * @var mysqli
     */
    protected $mysql = false;

    /**
     * LTI Tool Provider for handling authentication and consumer/user management
     * @var ToolProvider
     */
    protected $toolProvider;

    /**
     * Generator for LTI Configuration XML files
     * @var Generator
     */
    protected $generator;

    /**
     * Log file manager
     * @var Log
     */
    protected $logger = false;

    /**
     * Queue of delayed log messages (waiting for a logger instance)
     * @return array
     */
    protected $logQueue = [];

    /**
     * Provide serialization support for the Toolbox
     *
     * This allows a Toolbox to be stored in the `$_SESSION` variables.
     *
     * Caveat emptor: because `mysqli` objects can not be serialized,
     * serialization is limited to storing a reference to the configuration file
     * that generated this object, which will be reaccessed (along with cached
     * configuration metadata) when the object is unserialized.
     *
     * @return string
     */
    public function serialize()
    {
        return serialize([
            'config' => $this->config(static::TOOL_CONFIG_FILE)
        ]);
    }

    /**
     * Provide serialization support for Toolbox
     *
     * This allows a Toolbox to be stored in the `$_SESSION` variables.
     *
     * @see Toolbox::serialize() `Toolbox::serialize()` has more information on the
     *      specifics of the serialization approach.
     *
     * @param  string $serialized A Toolbox object serialized by `Toolbox::serialize()`
     * @return Toolbox
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->loadConfiguration($data['config']);
    }

    /**
     * Create a Toolbox instance from a configuration file
     *
     * @param  string $configFilePath Path to the configuration file
     * @param  boolean $forceRecache Whether or not to rely on cached
     *     configuration metadata or to force a refresh from the configuration
     *     file
     * @return Toolbox
     */
    public static function fromConfiguration($configFilePath, $forceRecache = false)
    {
        return new static($configFilePath, $forceRecache);
    }

    /**
     * Construct a Toolbox instance from a configuration file
     *
     * @see Toolbox::fromConfiguration() Use `Toolbox::fromConfiguration()`
     *
     * @param string $configFilePath
     * @param boolean $forceRecache
     */
    private function __construct($configFilePath, $forceRecache = false)
    {
        $this->loadConfiguration($configFilePath, $forceRecache);
    }

    /**
     * Update a Toolbox instance from a configuration file
     *
     * @see Toolbox::fromConfiguration() Use `Toolbox::fromConfiguration()`
     *
     * @param  string $configFilePath
     * @param  boolean $forceRecache
     * @return void
     */
    protected function loadConfiguration($configFilePath, $forceRecache = false)
    {
        if ($forceRecache) {
            $this->log("Resetting LTI configuration from $configFilePath");
            $this->config(static::TOOL_CONFIG_FILE, realpath($configFilePath));
        }

        /* load the configuration file */
        $config = new ConfigXML($configFilePath);

        /* configure database connections */
        $this->setMySQL($config->newInstanceOf(mysqli::class, '/config/mysql'));

        /* configure metadata caching */
        $id = $config->toString('/config/tool/id');
        if (empty($id)) {
            $id = basename(dirname($configFilePath)) . '_' . md5(__DIR__ . file_get_contents($configFilePath));
            $this->log("    Automatically generated ID $id");
        }
        $this->setMetadata(new AppMetadata($this->mysql, $id, self::TOOL_METADATA_TABLE));

        /* update metadata */
        if ($forceRecache ||
            empty($this->config(static::TOOL_ID)) ||
            empty($this->config(static::TOOL_LAUNCH_URL)) ||
            empty($this->config(static::TOOL_CONFIG_FILE))) {
            $this->configToolMetadata($config, $id);
        }

        /* configure logging */
        if ($forceRecache || empty($this->config(static::TOOL_LOG))) {
            $this->configLog($config);
        }
        $this->setLog(Log::singleton('file', $this->config(static::TOOL_LOG)));

        /* configure tool provider */
        if ($forceRecache || empty($this->config(static::TOOL_HANDLER_URLS))) {
            $this->configToolProvider($config);
        }

        /* configure API access */
        if ($forceRecache || empty($this->config(static::TOOL_CANVAS_API))) {
            $this->configApi($config);
        }
    }

    /**
     * Configure the tool metadata from a configuration file
     * @param ConfigXML $config Configuration file object
     * @param string $id Unique, potentially auto-generated tool ID
     * @return void
     */
    protected function configToolMetadata(ConfigXML $config, $id)
    {
        $tool = $config->toArray('/config/tool')[0];

        $this->config(static::TOOL_ID, $id);
        $this->config(static::TOOL_NAME, (empty($tool['name']) ? $id : $tool['name']));
        $configPath = dirname($this->config(static::TOOL_CONFIG_FILE));

        if (!empty($tool['description'])) {
            $this->config(static::TOOL_DESCRIPTION, $tool['description']);
        } else {
            $this->clearConfig(static::TOOL_DESCRIPTION);
        }

        if (!empty($tool['icon'])) {
            $this->config(static::TOOL_ICON_URL, (
                file_exists("$configPath/{$tool['icon']}") ?
                    DataUtilities::URLfromPath("$configPath/{$tool['icon']}") :
                    $tool[self::ICON]
            ));
        } else {
            $this->clearConfig(static::TOOL_ICON_URL);
        }

        $this->config(static::TOOL_LAUNCH_PRIVACY, (
            empty($tool['launch-privacy']) ?
                self::DEFAULT_LAUNCH_PRIVACY :
                $tool['launch-privacy']
        ));

        if (!empty($tool['domain'])) {
            $this->config(static::TOOL_DOMAIN, $tool['domain']);
        } else {
            $this->clearConfig(static::TOOL_DOMAIN);
        }

        $this->config(static::TOOL_LAUNCH_URL, (
            empty($tool['authenticate']) ?
                DataUtilities::URLfromPath($_SERVER['SCRIPT_FILENAME']) :
                DataUtilities::URLfromPath("$configPath/{$tool['authenticate']}")
        ));

        $this->log("    Tool metadata configured");
    }

    /**
     * Configure the logger object from a configuration file
     *
     * This will also flush any backlog of queued messages that have been
     * waiting for a logger object to be ready.
     *
     * @param ConfigXML $config Configuration file object
     * @return void
     */
    protected function configLog(ConfigXML $config)
    {
        $configPath = dirname($this->config(static::TOOL_CONFIG_FILE));
        $log = "$configPath/" . $config->toString('/config/tool/log');
        shell_exec("touch \"$log\"");
        $this->config(static::TOOL_LOG, realpath($log));
        $this->flushLogQueue();
    }

    /**
     * Configure tool provider object from configuration file
     * @param ConfigXML $config Configuration file object
     * @return void
     */
    protected function configToolProvider(ConfigXML $config)
    {
        $handlers = $config->toArray('/config/tool/handlers')[0];
        if (empty($handlers) || !is_array($handlers)) {
            throw new ConfigurationException(
                'At least one handler/URL pair must be specified',
                ConfigurationException::TOOL_PROVIDER
            );
        }
        foreach ($handlers as $request => $path) {
            $handlers[$request] = DataUtilities::URLfromPath(dirname($this->config(static::TOOL_CONFIG_FILE)) . "/$path");
        }
        $this->config(static::TOOL_HANDLER_URLS, $handlers);
        $this->log('    Tool provider handler URLs configured');
    }

    /**
     * Configure API access object from configuration file
     * @param ConfigXML $config Configuration file object
     * @return void
     */
    protected function configApi(ConfigXML $config)
    {
        $this->config(static::TOOL_CANVAS_API, $config->toArray('/config/canvas')[0]);
        if (empty($this->config(static::TOOL_CANVAS_API))) {
            throw new ConfigurationException(
                'Canvas API credentials must be provided',
                ConfigurationException::CANVAS_API_MISSING
            );
        }
        $this->log('    Canvas API credentials configured');
    }

    /**
     * Update toolbox configuration metadata object
     *
     * @param AppMetadata $metadata
     */
    public function setMetadata(AppMetadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get the toolbox configuration metadata object
     *
     * @return AppMetadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Access or update a specific configuration metadata key/value pair
     *
     * The `TOOL_*` constants refer to keys used by the Toolbox by default.
     *
     * @param  string $key The metadata key to look up/create/update
     * @param  mixed $value (Optional) If not present (or `null`), the current
     *     metadata is returned. If present, the metadata is created/updated
     * @return mixed If not updating the metadata, the metadata (if any)
     *     currently stored
     */
    public function config($key, $value = null)
    {
        if ($value !== null) {
            $this->metadata[$key] = $value;
        } else {
            return $this->metadata[$key];
        }
    }

    /**
     * Wipe a particular configuration key from storage
     * @param string $key
     * @return boolean `TRUE` if cleared, `FALSE` if not found
     */
    public function clearConfig($key)
    {
        if (isset($this->metadata[$key])) {
            unset($this->metadata[$key]);
            return true;
        }
        return false;
    }

    /**
     * Update the ToolProvider object
     *
     * @param ToolProvider $toolProvider
     */
    public function setToolProvider(ToolProvider $toolProvider)
    {
        $this->toolProvider = $toolProvider;
    }

    /**
     * Get the ToolProvider object
     *
     * This does some just-in-time initialization, so that if the ToolProvider
     * has not yet been accessed, it will be instantiated and initialized by this
     * method.
     *
     * @return ToolProvider
     */
    public function getToolProvider()
    {
        if (empty($this->toolProvider)) {
            $this->setToolProvider(
                new ToolProvider(
                    $this->mysql,
                    $this->metadata['TOOL_HANDLER_URLS']
                )
            );
        }
        return $this->toolProvider;
    }

    /**
     * Authenticate an LTI launch request
     *
     * @return void
     * @codingStandardsIgnoreStart
     */
    public function lti_authenticate()
    {
        /* @codingStandardsIgnoreEnd */
        $this->getToolProvider()->handle_request();
    }

    /**
     * Are we (or should we be) in the midst of authenticating an LTI launch request?
     *
     * @return boolean
     * @codingStandardsIgnoreStart
     */
    public function lti_isLaunching()
    {
        /* @codingStandardsIgnoreEnd */
        return !empty($_POST['lti_message_type']);
    }

    /**
     * Create a new Tool consumer
     *
     * @see ToolProvider::createConsumer() Pass-through to `ToolProvider::createConsumer()`
     *
     * @param  string $name Human-readable name
     * @param  string $key (Optional) Consumer key (unique within the tool provider)
     * @param  string $secret (Optional) Shared secret
     * @return boolean Whether or not the consumer was created
     * @codingStandardsIgnoreStart
     */
    public function lti_createConsumer($name, $key = false, $secret = false)
    {
        /* @codingStandardsIgnoreEnd */
        if ($this->getToolProvider()->createConsumer($name, $key, $secret)) {
            $this->log("Created consumer $name");
            return true;
        } else {
            $this->log("Could not recreate consumer '$name', consumer already exists");
            return false;
        }
    }

    /**
     * Get the list of consumers for this tool
     *
     * @see ToolProvider::getConsumers() Pass-through to `ToolProvider::getConsumers()`
     *
     * @return LTI_Consumer[]
     * @codingStandardsIgnoreStart
     */
    public function lti_getConsumers()
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getToolProvider()->getConsumers();
    }

    /**
     * Update the API interaction object
     *
     * @param CanvasPest $api
     */
    public function setAPI(CanvasPest $api)
    {
        $this->api = $api;
    }

    /**
     * Get the API interaction object

     * @return CanvasPest
     */
    public function getAPI()
    {
        if (empty($this->api)) {
            if (!empty($this->config(static::TOOL_CANVAS_API)['token'])) {
                $this->setAPI(new CanvasPest(
                    'https://' . $_SESSION[ToolProvider::class]['canvas']['api_domain'] . '/api/v1',
                    $this->config(static::TOOL_CANVAS_API)['token']
                ));
            } else {
                throw new ConfigurationException(
                    'Canvas URL and Token required',
                    ConfigurationException::CANVAS_API_INCORRECT
                );
            }
        }
        return $this->api;
    }

    /**
     * Make a GET request to the API
     *
     * @link https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/canvaspest/master/doc/classes/smtech.CanvasPest.CanvasPest.html#method_get Pass-through to CanvasPest::get()
     * @param  string $url
     * @param  string[] $data (Optional)
     * @param  string[] $headers (Optional)
     * @return \smtech\CanvasPest\CanvasObject|\smtech\CanvasPest\CanvasArray
     * @codingStandardsIgnoreStart
     */
    public function api_get($url, $data = [], $headers = [])
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getAPI()->get($url, $data, $headers);
    }

    /**
     * Make a POST request to the API
     *
     * @link https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/canvaspest/master/doc/classes/smtech.CanvasPest.CanvasPest.html#method_post Pass-through to CanvasPest::post()
     * @param  string $url
     * @param  string[] $data (Optional)
     * @param  string[] $headers (Optional)
     * @return \smtech\CanvasPest\CanvasObject|\smtech\CanvasPest\CanvasArray
     * @codingStandardsIgnoreStart
     */
    public function api_post($url, $data = [], $headers = [])
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getAPI()->post($url, $data, $headers);
    }

    /**
     * Make a PUT request to the API
     *
     * @link https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/canvaspest/master/doc/classes/smtech.CanvasPest.CanvasPest.html#method_put Pass-through to CanvasPest::put()
     * @param  string $url
     * @param  string[] $data (Optional)
     * @param  string[] $headers (Optional)
     * @return \smtech\CanvasPest\CanvasObject|\smtech\CanvasPest\CanvasArray
     * @codingStandardsIgnoreStart
     */
    public function api_put($url, $data = [], $headers = [])
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getAPI()->put($url, $data, $headers);
    }

    /**
     * Make a DELETE request to the API
     *
     * @link https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/canvaspest/master/doc/classes/smtech.CanvasPest.CanvasPest.html#method_delete Pass-through to CanvasPest::delete()
     * @param  string $url
     * @param  string[] $data (Optional)
     * @param  string[] $headers (Optional)
     * @return \smtech\CanvasPest\CanvasObject|\smtech\CanvasPest\CanvasArray
     * @codingStandardsIgnoreStart
     */
    public function api_delete($url, $data = [], $headers = [])
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getAPI()->delete($url, $data, $headers);
    }

    /**
     * Set MySQL connection object
     *
     * @param mysqli $mysql
     */
    public function setMySQL(mysqli $mysql)
    {
        $this->mysql = $mysql;
    }

    /**
     * Get MySQL connection object
     *
     * @return mysqli
     */
    public function getMySQL()
    {
        return $this->mysql;
    }

    /**
     * Make a MySQL query
     *
     * @link http://php.net/manual/en/mysqli.query.php Pass-through to `mysqli::query()`
     * @param string $query
     * @param int $resultMode (Optional, defaults to `MYSQLI_STORE_RESULT`)
     * @return mixed
     * @codingStandardsIgnoreStart
     */
    public function mysql_query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        /* @codingStandardsIgnoreEnd */
        return $this->getMySQL()->query($query, $resultMode);
    }

    /**
     * Check if the logger object is ready for use
     * @return boolean `TRUE` if ready, `FALSE` otherwise
     */
    protected function logReady()
    {
        return is_a($this->logger, Log::class);
    }

    /**
     * Set log file manager
     *
     * @param Log $log
     */
    public function setLog(Log $log)
    {
        $this->logger = $log;
    }

    /**
     * Get log file manager
     *
     * @return Log
     */
    public function getLog()
    {
        return $this->logger;
    }

    /**
     * Queue a message for delayed logging
     * @param string $message
     * @param string $priority
     * @return void
     */
    protected function queueLog($message, $priority = null)
    {
        $this->logQueue[] = ['message' => $message, 'priority' => $priority];
    }

    /**
     * Flush the delayed log queue
     * @return void
     */
    protected function flushLogQueue()
    {
        if ($this->logReady() && !empty($this->logQueue)) {
            foreach ($this->logQueue as $entry) {
                $this->getLog()->log($entry['message'], $entry['priority']);
            }
            $this->logQueue = [];
        }
    }

    /**
     * Add a message to the tool log file
     *
     * If no logger object is ready, the message will be queued for delayed
     * logging until a logger object is ready.
     *
     * @link https://pear.php.net/package/Log/docs/1.13.1/Log/Log_file.html#methodlog
     *      Pass-throgh to `Log_file::log()`
     *
     * @param string $message
     * @param string $priority (Optional, defaults to `PEAR_LOG_INFO`)
     * @return boolean Success
     */
    public function log($message, $priority = null)
    {
        if ($this->logReady()) {
            $this->flushLogQueue();
            return $this->getLog()->log($message, $priority);
        } else {
            $this->queueLog($message, $priority);
        }
    }

    /**
     * Set the LTI Configuration generator
     *
     * @param Generator $generator
     */
    public function setGenerator(Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get the LTI Configuration generator
     *
     * @return Generator
     */
    public function getGenerator()
    {
        try {
            if (empty($this->generator)) {
                $this->setGenerator(
                    new Generator(
                        $this->config(static::TOOL_NAME),
                        $this->config(static::TOOL_ID),
                        $this->config(static::TOOL_LAUNCH_URL),
                        (empty($this->config(static::TOOL_DESCRIPTION)) ? false : $this->config(static::TOOL_DESCRIPTION)),
                        (empty($this->config(static::TOOL_ICON_URL)) ? false : $this->config(static::TOOL_ICON_URL)),
                        (empty($this->config(static::TOOL_LAUNCH_PRIVACY)) ? LaunchPrivacy::USER_PROFILE() : $this->config(static::TOOL_LAUNCH_PRIVACY)),
                        (empty($this->config(static::TOOL_DOMAIN)) ? false : $this->config(static::TOOL_DOMAIN))
                    )
                );
            }
        } catch (LTIConfigGeneratorException $e) {
            throw new ConfigurationException(
                $e->getMessage(),
                ConfigurationException::TOOL_PROVIDER
            );
        }
        return $this->generator;
    }

    /**
     * Get the LTI configuration XML
     *
     * @link https://htmlpreview.github.io/?https://raw.githubusercontent.com/smtech/lti-configuration-xml/master/doc/classes/smtech.LTI.Configuration.Generator.html#method_saveXML Pass-through to `Generator::saveXML()`
     *
     * @return string
     */
    public function saveConfigurationXML()
    {
        try {
            return $this->getGenerator()->saveXML();
        } catch (LTIConfigGeneratorException $e) {
            throw new ConfigurationException(
                $e->getMessage(),
                ConfigurationException::TOOL_PROVIDER
            );
        }
    }
}
