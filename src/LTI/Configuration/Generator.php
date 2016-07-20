<?php

namespace smtech\ReflexiveCanvasLTI\LTI\Configuration;

use DOMDocument;

use smtech\ReflexiveCanvasLTI\LTI\Configuration\LaunchPrivacy;
use smtech\ReflexiveCanvasLTI\LTI\Configuration\Option;
use smtech\ReflexiveCanvasLTI\Exception\ConfigurationException;

/**
 * Generate a valid LTI Tool Provider configuration XML document to facilitate
 * tool placements within a Tool Consumer.
 *
 * With minimal information (a name, ID and launch URL), the default
 * configuration will provide a course navigation placement that uses the name
 * as the link text and the launch URL as the the link URL, defaulting to
 * 'anonymous' launch privacy.
 *
 * @see https://www.edu-apps.org/build_xml.html Edu Apps XML Config Builder
 *      provides an interface for generating static configuration XML files.
 *
 * @author Seth Battis <SethBattis@stmarksschool.org>
 */
class Generator {

    const
        LTICC = 'http://www.imsglobal.org/xsd/imslticc_v1p0',
        XMLNS = 'http://www.w3.org/2000/xmlns/',
        BLTI =  'http://www.imsglobal.org/xsd/imsbasiclti_v1p0',
        LTICM = 'http://www.imsglobal.org/xsd/imslticm_v1p0',
        LTICP = 'http://www.imsglobal.org/xsd/imslticp_v1p0',
        XSI =   'http://www.w3.org/2001/XMLSchema-instance';

    protected
        $name,
        $id,
        $launchURL,
        $description = false,
        $iconURL = false,
        $custom = [],
        $launchPrivacy = false,
        $domain = false,
        $options = [];

    public function __construct(
        $name,
        $id,
        $launchURL,
        $description = null,
        $iconURL = null,
        $launchPrivacy = null,
        $domain = null
    ) {
        $this->setName($name);
        $this->setID($id);
        $this->setLaunchURL($launchURL);
        $this->setDescription($description);
        $this->setIconURL($iconURL);
        $this->setLaunchPrivacy($launchPrivacy);
        $this->setDomain($domain);
    }

    public function setName($name) {
        if (empty((string) $name)) {
            throw new ConfigurationException(
                'The configuration must specify a non-empty name for the Tool Provider.',
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->name = (string) $name;
        }
    }

    /**
     * [setID description]
     *
     * TODO validate/tokenize the actual ID
     *
     * @param string $id [description]
     */
    public function setID($id) {
        if (empty((string) $id)) {
            throw new ConfigurationException(
                'The configuration must specify a non-empty (and globally unique) ID for the Tool Provider.',
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->id = (string) $id;
        }
    }

    /**
     * [setLaunchURL description]
     *
     * TODO validate the actual URL?
     *
     * @param string $launchURL [description]
     */
    public function setLaunchURL($launchURL) {
        if (empty((string) $launchURL)) {
            throw new ConfigurationException(
                'The configuration must specify a valid launch URL for the Tool Provider.',
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->launchURL = (string) $launchURL;
        }
    }

    public function setDescription($description) {
        $this->description = (empty((string) $description) ? false : (string) $description);
    }

    /**
     * [setIconURL description]
     *
     * TODO validate the actual URL?
     *
     * @param [type] $iconURL [description]
     */
    public function setIconURL($iconURL) {
        $this->iconURL = (empty((string) $iconURL) ? false : (string) $iconURL);
    }

    public function setLaunchPrivacy($launchPrivacy) {
        if (!LaunchPrivacy::isValid($launchPrivacy)) {
            throw new ConfigurationException(
                "Invalid launch privacy setting '$launchPrivacy'",
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->launchPrivacy = (empty($launchPrivacy) ? LaunchPrivacy::ANONYMOUS() : $launchPrivacy);
        }
    }

    public function setDomain($domain) {
        $this->domain = (empty($domain) ? false : $domain);
    }

    public function setOption($option, array $properties) {
        if (!Option::isValid($option)) {
            throw new ConfigurationException(
                "Invalid configuration option '$option'",
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->options[$option] = $properties;
        }
    }

    public function setOptionProperty($option, $property, $value) {
        if (!Option::isValid($option)) {
            throw new ConfigurationException(
                "Invalid configuration option '$option'",
                ConfigurationException::TOOL_PROVIDER
            );
        } else {
            $this->options[$option][$property] = $value;
        }
    }

    /**
    * [saveXML description]
     *
     * @see https://www.edu-apps.org/build_xml.html Edu App XML Config Builder
     *      for a more complete `config.xml`
     *
     * @return string [description]
     */
    public function saveXML() {

        $config = new DOMDocument('1.0', 'UTF-8');
        $config->formatOutput = true;

        $cartridge = $config->createElementNS(self::LTICC, 'cartridge_basiclti_link');
        $config->appendChild($cartridge);
        $cartridge->setAttributeNS(self::XMLNS, 'xmlns:blti', self::BLTI);
        $cartridge->setAttributeNS(self::XMLNS, 'xmlns:lticm', self::LTICM);
        $cartridge->setAttributeNS(self::XMLNS, 'xmlns:lticp', self::LTICP);
        $cartridge->setAttributeNS(self::XMLNS, 'xmlns:xsi', self::XSI);
        $cartridge->setAttributeNS(
            self::XSI,
            'xsi:schemaLocation',
            self::LTICC . ' ' . self::LTICC . '.xsd ' .
            self::BLTI . ' ' . self::BLTI . '.xsd ' .
            self::LTICM . ' ' . self::LTICM . '.xsd ' .
            self::LTICP . ' ' . self::LTICP . '.xsd'
        );

        $cartridge->appendChild($config->createElementNS(
            self::BLTI,
            'blti:title',
            $this->name
        ));

        /*
         * TODO CDATA wrapper?
         */
        if ($this->description) {
            $cartridge->appendChild($config->createElementNS(
                self::BLTI,
                'blti:description',
                $this->description
            ));
        }

        if ($this->iconURL) {
            $cartridge->appendChild($config->createElementNS(
                self::BLTI,
                'blti:icon',
                $this->iconURL
            ));
        }

        $cartridge->appendChild($config->createElementNS(
            self::BLTI,
            'blti:launch_url',
            $this->launchURL
        ));

        $extensions = $config->createElementNS(self::BLTI, 'blti:extensions');
        $cartridge->appendChild($extensions);
        $extensions->setAttribute('platform', 'canvas.instructure.com');

        $property = $config->createElementNS(
            self::LTICM,
            'lticm:property',
            $this->id
        );
        $property->setAttribute('name', 'tool_id');
        $extensions->appendChild($property);

        $property = $config->createElementNS(
            self::LTICM,
            'lticm:property',
            $this->launchPrivacy
        );
        $property->setAttribute('name', 'privacy_level');
        $extensions->appendChild($property);

        if (!empty($this->domain)) {
            $property = $config->createElementNS(
                self::LTICM,
                'lticm:property',
                $this->domain
            );
            $property->setAttribute('name', 'domain');
            $extensions->appendChild($property);
        }

        /* if no options are configured, create a default course navigation option */
        if (empty($this->options)) {
            $extensions->appendChild($this->getOptionsElement(
                $config,
                Option::COURSE_NAVIGATION(),
                []
            ));
        } else {
            foreach ($this->options as $option => $properties) {
                $extensions->appendChild($this->getOptionsElement(
                    $config,
                    $option,
                    $properties
                ));
            }
        }

        $bundle = $config->createElement('cartridge_bundle');
        $cartridge->appendChild($bundle);
        $bundle->setAttribute('identiferref', 'BLT001_Bundle');

        $icon = $config->createElement('cartridge_icon');
        $cartridge->appendChild($icon);
        $icon->setAttribute('identifierref', 'BLT001_Icon');

        return $config->saveXML();
    }

    private function getOptionsElement(DOMDocument $config, Option $option, array $properties) {
        $options = $config->createElementNS(self::LTICM, 'lticm:options');
        $options->setAttribute('name', $option);

        /* inherit link text and launch URL properties if not specified */
        if (!array_key_exists('text', $properties)) {
            $properties['text'] = $this->name;
        }
        if (!array_key_exists('url', $properties)) {
            $properties['url'] = $this->launchURL;
        }

        foreach ($properties as $name => $value) {
            $property = $config->createElementNS(
                self::LTICM,
                'lticm:property',
                $value
            );
            $property->setAttribute('name', $name);
            $options->appendChild($property);
        }

        return $options;
    }
}
