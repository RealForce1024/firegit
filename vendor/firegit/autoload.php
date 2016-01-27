<?php
include_once __DIR__.'/../../conf/const.php';

spl_autoload_register('firegit_autoload');
function firegit_autoload($className) {
    $arr = explode("\\", $className);
    if (count($arr) > 2 && $arr[0] == 'firegit') {
        array_shift($arr);
        if ($arr[0] == 'app') {
            array_shift($arr);
            $path = APP_ROOT.implode('/', $arr).'.php';
        } else {
            $path = FR_ROOT.implode('/', $arr).'.php';
        }
        if (is_readable($path)) {
            require_once $path;
        }
    }
}