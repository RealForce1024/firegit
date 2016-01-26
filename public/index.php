<?php

ini_set('error_display', true);
error_reporting(E_ALL);

$_root = dirname(__DIR__);
define('CTL_ROOT', $_root . '/ctl/');
define('VIEW_ROOT', $_root . '/view/');
define('FR_ROOT', $_root . '/vendor/firegit/');

require_once FR_ROOT . '/http/Request.php';
\firegit\http\Request::dispatch();