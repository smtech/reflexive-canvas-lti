<?php

/*

    A responsible developer would restrict access to this page using, for
    example, HTTP Auth or some other authentication approach. Random folks
    with URLs shouldn't be able to reset _your_ authentication system! And
    that's what this page does: it reload `config.xml` and then creates
    a dummy Tool Consumer (TC) and then presents their credentials to
    that browser. Woo! That would be bad if it got into the wrong hands, huh?

 */

require_once 'common.inc.php';

use smtech\ReflexiveCanvasLTI\Toolbox;

/* reset toolbox configuration from configuration file */
$_SESSION['toolbox'] = Toolbox::fromConfiguration(CONFIG_FILE, true);
$toolbox =& $_SESSION['toolbox'];

$toolbox->createConsumer('Example Consumer');

require 'reset-summary.inc.php';
