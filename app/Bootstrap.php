<?php
namespace firegit\app;

class Bootstrap
{
    public static function start()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', false);

        $uri = $_SERVER['REQUEST_URI'];
        $_SERVER['REQUEST_RAWURI'] = $uri;
        if (preg_match('#^\/([a-zA-Z][a-zA-Z0-9\-\_]{5,20})\/([a-zA-Z][a-zA-Z0-9\-\_]+)(\.git)?(\/.*)?$#', $uri, $ms)) {
            $dir = GIT_REPO . '/' . $ms[1] . '/' . $ms[2] . '.git';

            if (is_dir($dir)) {
                $arr = explode('/', trim($uri, '/'));

                // 带有git的自动跳转
                if (isset($ms[3]) && $ms[3] == '.git') {
                    header('Location: /' . $ms[1] . '/' . $ms[2] . (isset($ms[4]) ? $ms[4] : ''));
                    exit();
                }
                $len = count($arr);
                $_SERVER['GIT_GROUP'] = $ms[1];
                $_SERVER['GIT_NAME'] = $ms[2];
                if ($len == 2) {
                    $_SERVER['REQUEST_URI'] = '/git/';
                    $_SERVER['GIT_PATH'] = '';
                    $_SERVER['GIT_BRANCH'] = 'master';
                } else {
                    if (($len == 3 && ($arr[2] == 'branches' || $arr[2][0] == '_' )) || $len > 3) {
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
                            'contributors',
                            '_new_branch',
                            '_del_branch',
                        ))) {

                            if (in_array($arr[2], array('tree', 'blob', 'raw'))) {
                                $_SERVER['GIT_BRANCH'] = $arr[3];
                                $_SERVER['GIT_PATH'] = implode('/', array_slice($arr, 4));
                            } else {
                                $_SERVER['GIT_BRANCH'] = implode('/', array_slice($arr, 3));
                                $_SERVER['GIT_PATH'] = '';
                            }
                            if (!$_SERVER['GIT_BRANCH']) {
                                $_SERVER['GIT_BRANCH'] = 'master';
                            }

                            $_SERVER['REQUEST_URI'] = '/git/' . $arr[2];
                        }
                    }
                }
            }
        }
        \firegit\http\Dispatcher::dispatch();
    }
}