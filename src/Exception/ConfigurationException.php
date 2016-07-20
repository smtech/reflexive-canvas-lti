<?php

namespace smtech\ReflexiveCanvasLTI;

use smtech\ReflexiveCanvasLTI\Exception\ReflexiveCanvasLTIException;

/**
 * Exceptions relating to the processing of the Environment configuration file
 *
 * @author Seth Battis <SethBattis@stmarksschool.org>
 *
 * @see \smtech\ReflexiveCanvasLTI\Environment Environment
 */
class ConfigurationException extends ReflexiveCanvasLTIException {
    const TOOL_PROVIDER = 1;
    const CANVAS_API_MISSING = 2;
    const CANVAS_API_INCORRECT = 3;
    const MYSQL = 4;
    const LOG = 5;
}
