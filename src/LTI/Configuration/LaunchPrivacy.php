<?php

namespace smtech\ReflexiveCanvasLTI\LTI\Configuration;

use MyCLabs\Enum\Enum;

class LaunchPrivacy extends Enum {
    const USER_PROFILE = 'public';
    const NAME_ONLY = 'name_only';
    const ANONYMOUS = 'anonymous';
}
