<?php

require_once 'common.inc.php';

session_start();

/* test for authentication before doing anything! */
if (empty($_SESSION['lti']) || !$_SESSION['lti']->isAuthenticated()) {
    $message = 'You are not authenticated.';
    require 'error.inc.php';
}
