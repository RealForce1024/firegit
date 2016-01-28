<?php
$_root = dirname(__DIR__);
define('APP_ROOT', $_root.'/app/');
define('CTL_ROOT', $_root . '/app/ctl/');
define('VIEW_ROOT', $_root . '/app/view/');
define('FR_ROOT', $_root . '/vendor/firegit/');
define('LOG_ROOT', $_root.'/log/');
define('BIN_ROOT', $_root.'/bin/');
define('VENDOR_ROOT', $_root.'/vendor/');


define('GIT_REPO', '/home/git/repos/');
define('GIT_USER', 'git');
define('GIT_GROUP', 'git');
define('GIT_BRANCH_RULE', '#^[a-z][a-z0-9\_\-]{5,30}$#');
define('GIT_ZERO_HASH', str_repeat('0', 40));