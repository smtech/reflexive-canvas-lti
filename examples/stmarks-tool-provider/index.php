<?php

require_once 'common.inc.php';

use smtech\StMarksReflexiveCanvasLTIExample\Toolbox;
use smtech\ReflexiveCanvasLTI\LTI\ToolProvider;
use smtech\ReflexiveCanvasLTI\Exception\ConfigurationException;

define('ACTION_CONFIG', 'config');
define('ACTION_INSTALL', 'install');
define('ACTION_CONSUMERS', 'consumers');
define('ACTION_UNSPECIFIED', false);

/* store any requested actions for future handling */
$action = (empty($_REQUEST['action']) ?
    ACTION_UNSPECIFIED :
    strtolower($_REQUEST['action'])
);

/* action requests only come from outside the LTI! */
if ($action) {
    unset($_SESSION[ToolProvider::class]);
}

/* authenticate LTI launch request, if present */
if ($toolbox->lti_isLaunching()) {
    $toolbox->resetSession();
    $toolbox->lti_authenticate();
    exit;
}

/* if authenticated LTI launch, get the current user profile */
if (!empty($_SESSION[ToolProvider::class]['canvas'])) {
    try {
        $profile = $toolbox->api_get('users/' . $_SESSION[ToolProvider::class]['canvas']['user_id'] . '/profile');
    } catch (Exception $e) {
        $toolbox->smarty_addMessage(
            'Error',
            json_decode($e->getMessage(), true),
            NotificationMessage::DANGER
        );
    }
    $toolbox->smarty_assign([
        'profile' => $profile
    ]);
    $toolbox->smarty_display('home.tpl');
    exit;

/* if not authenticated, default to showing credentials */
} else {
    $action = (empty($action) ?
        ACTION_CONFIG :
        $action
    );
}

/* process any actions */
switch ($action) {
    /* reset cached install data from config file */
    case ACTION_INSTALL:
        $_SESSION['toolbox'] = Toolbox::fromConfiguration(CONFIG_FILE, true);
        $toolbox =& $_SESSION['toolbox'];

        /* test to see if we can connect to the API */
        try {
            $toolbox->getAPI();
        } catch (ConfigurationException $e) {
            /* if there isn't an API token in config.xml, are there OAuth credentials? */
            if ($e->getCode() === ConfigurationException::CANVAS_API_INCORRECT) {
                $toolbox->interactiveGetAccessToken();
                exit;
            } else { /* no (understandable) API credentials available -- doh! */
                throw $e;
            }
        }

        /* finish by opening consumers control panel */
        header('Location: consumers.php');
        exit;

    /* show LTI configuration XML file */
    case ACTION_CONFIG:
        header('Content-type: application/xml');
        echo $toolbox->saveConfigurationXML();
        exit;
}
