<?php
namespace firegit\app;

class Bootstrap
{
    public static function start()
    {
        ini_set('error_display', true);
        error_reporting(E_ALL);
        ini_set('error_log', LOG_ROOT . '/php_error.log');

        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#^\/([a-zA-Z][a-zA-Z0-9\-\_]{5,20})\/([a-zA-Z][a-zA-Z0-9\-\_]+)(\.git)?(\/.*)?$#', $uri, $ms)) {
            $arr = explode('/', trim($uri, '/'));
            $dir = GIT_REPO . '/' . $arr[0] . '/' . $arr[1] . '.git';
            if (is_dir($dir)) {
                // 带有git的自动跳转
                if (isset($ms[3]) && $ms[3] == '.git') {
                    header('Location: /' . $ms[1] . '/' . $ms[2] . isset($ms[4]) ? $ms[4] : '');
                    exit();
                }
                $len = count($arr);
                $_SERVER['GIT_GROUP'] = $arr[0];
                $_SERVER['GIT_NAME'] = $arr[1];
                if ($len == 2) {
                    $_SERVER['REQUEST_URI'] = '/git/';
                    $_SERVER['GIT_PATH'] = '';
                    $_SERVER['GIT_BRANCH'] = 'master';
                } else {
                    if ( ($len == 3 && $arr[2] == 'branches') || $len > 3) {
                        if (in_array($arr[2], array(
                            'tree',
                            'blob',
                            'raw',
                            'blame',
                            'history',
                            'commits',
                            'commit',
                            'branches',
                            'branch',
                            'contributors'
                        ))) {
                            $_SERVER['GIT_BRANCH'] = isset($arr[3]) ? $arr[3] : 'master';
                            $_SERVER['GIT_PATH'] = implode('/', array_slice($arr, 4));
                            $_SERVER['REQUEST_URI'] = '/git/' . $arr[2];
                        }
                    }
                }
            }
        }

        \firegit\http\Dispatcher::dispatch();
    }
}