<?php

namespace smtech\ReflexiveCanvasLTI\LTI\Configuration;

use MyCLabs\Enum\Enum;

class Option extends Enum {
    const EDITOR = 'editor';
    const LINK_SELECTION = 'link_selection';
    const HOMEWORK_SUBMISSION = 'homework_submission';
    const COURSE_NAVIGATION = 'course_navigation';
    const ACCOUNT_NAVIGATION = 'account_navigation';
    const USER_NAVIGATION = 'user_navigation';
}
