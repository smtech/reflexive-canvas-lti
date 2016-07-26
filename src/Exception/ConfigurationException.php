<?php

namespace smtech\ReflexiveCanvasLTI\Exception;

use smtech\ReflexiveCanvasLTI\Exception\ReflexiveCanvasLTIException;

/**
 * Exceptions relating to configuration
 *
 * @author Seth Battis <SethBattis@stmarksschool.org>
 * @version v1.0
 */
class ConfigurationException extends ReflexiveCanvasLTIException
{
    const TOOL_PROVIDER = 1;
    const CANVAS_API_MISSING = 2;
    const CANVAS_API_INCORRECT = 3;
    const MYSQL = 4;
    const LOG = 5;
}
