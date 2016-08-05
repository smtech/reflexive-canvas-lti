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
    const
        DEFAULT_LAUNCH_PRIVACY = 'public',
        TOOL_METADATA_TABLE = 'tool_metadata';

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
            'config' => $this->metadata['TOOL_CONFIG_FILE']
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
        if ($forceRecache ||
            empty($this->metadata['TOOL_ID']) ||
            empty($this->metadata['TOOL_LAUNCH_URL']) ||
            empty($this->metadata['TOOL_CONFIG_FILE'])) {
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
     * The metadata keys used by the toolbox are prefixed `TOOL_`. Currently, the keys of interest are:
     *
     *   - `TOOL_NAME` is the human-readable name of the tool
     *   - `TOOL_ID` is the (ideally globally unique) identifier for the LTI tool provider
     *   - `TOOL_DESCRIPTION` is the human-readable description of the tool
     *   - `TOOL_ICON_URL` is the URL of the tool's icon image (if present)
     *   - `TOOL_DOMAIN` the domain from which Tool Consumer requests may emanate for the tool
     *   - `TOOL_LAUNCH_PRIVACY` is the level of information sharing between the LMS and the tool
     *   - `TOOL_LAUNCH_URL` is the URL of the script that will handle LTI authentication
     *   - `TOOL_HANDLER_URLS` stores an associative array of LTI request types and the URL that handles that request.
     *   - `TOOL_CONFIG_FILE` is the path to the configuration file from which this toolbox was generated
     *   - `TOOL_CANVAS_API` is an associative array of Canvas API credentials (`url` and `token`)
     *   - `TOOL_LOG` is the path to the tool's log file
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
     */
    public function lti_authenticate()
    {
        $this->getToolProvider()->handle_request();
    }

    /**
     * Are we (or should we be) in the midst of authenticating an LTI launch request?
     *
     * @return boolean
     */
    public function lti_isLaunching()
    {
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
     */
    public function lti_createConsumer($name, $key = false, $secret = false)
    {
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
     */
    public function lti_getConsumers()
    {
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
            $canvas = $this->metadata['TOOL_CANVAS_API'];
            if (!empty($canvas['url']) && !empty($canvas['token'])) {
                $this->setAPI(new CanvasPest(
                    "{$canvas['url']}/api/v1", // TODO this seems crude
                    $canvas['token']
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
     */
    public function api_get($url, $data = [], $headers = [])
    {
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
     */
    public function api_post($url, $data = [], $headers = [])
    {
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
     */
    public function api_put($url, $data = [], $headers = [])
    {
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
     */
    public function api_delete($url, $data = [], $headers = [])
    {
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
     */
    public function mysql_query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        return $this->getMySQL()->query($query, $resultMode);
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
     * Add a message to the tool log file
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
        return $this->getLog()->log($message, $priority);
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
                        $this->metadata['TOOL_NAME'],
                        $this->metadata['TOOL_ID'],
                        $this->metadata['TOOL_LAUNCH_URL'],
                        (empty($this->metadata['TOOL_DESCRIPTION']) ? false : $this->metadata['TOOL_DESCRIPTION']),
                        (empty($this->metadata['TOOL_ICON_URL']) ? false : $this->metadata['TOOL_ICON_URL']),
                        (empty($this->metadata['TOOL_LAUNCH_PRIVACY']) ? LaunchPrivacy::USER_PROFILE() : $this->metadata['TOOL_LAUNCH_PRIVACY']),
                        (empty($this->metadata['TOOL_DOMAIN']) ? false : $this->metadata['TOOL_DOMAIN'])
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
