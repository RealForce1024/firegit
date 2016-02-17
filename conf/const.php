<?php
define('SITE_ROOT', dirname(__DIR__));
define('APP_ROOT', SITE_ROOT.'/app/');
define('CTL_ROOT', SITE_ROOT . '/app/ctl/');
define('VIEW_ROOT', SITE_ROOT . '/app/view/');
define('FR_ROOT', SITE_ROOT . '/vendor/firegit/');
define('LOG_ROOT', SITE_ROOT.'/log/');
define('BIN_ROOT', SITE_ROOT.'/bin/');
define('VENDOR_ROOT', SITE_ROOT.'/vendor/');
define('CONF_ROOT', SITE_ROOT.'/conf/');
define('TMP_ROOT', SITE_ROOT.'/tmp/');


define('GIT_REPO', SITE_ROOT.'/repos/');
define('GIT_USER', 'work');
define('GIT_GROUP', 'work');
define('GIT_BRANCH_RULE', '#^[a-z][a-z0-9\_\-]{5,30}$#');
define('GIT_ZERO_HASH', str_repeat('0', 40));

define('HELPER_NS_PREFIX', '\\firegit\\app\\helper\\');