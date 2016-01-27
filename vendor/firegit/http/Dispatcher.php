<?php
namespace firegit\http;

class Dispatcher
{
    /**
     * 分配网址
     */
    public static function dispatch()
    {
        $uri = $_SERVER['REQUEST_URI'];
        trigger_error('uri:'.$uri, E_USER_NOTICE);

        $info = parse_url($uri);
        $path = $info['path'];
        $path = trim($path, '/');
        $parr = array_diff(explode('/', $path), array('', '.', '..'));

        $args = array();
        foreach ($parr as $key => $value) {
            if (preg_match('#^([1-9][0-9]*)|([a-z0-9]{32})$#', $value)) {
                $args[] = $value;
                unset($parr[$key]);
            }
        }
        $len = count($parr);
        $ctl = 'index';
        $method = 'index';
        if ($len == 1) {
            $ctl = array_pop($parr);
        } elseif ($len > 1) {
            $method = array_pop($parr);
            $ctl = array_pop($parr);
        }
        $ctlPath = CTL_ROOT . implode('/', $parr) . '/' . strtolower($ctl) . '/Controller.php';
        if (!is_readable($ctlPath)) {
            // 回溯一次
            $ctlPath = CTL_ROOT . implode('/', $parr).'/Controller.php';
            if (is_readable($ctlPath)) {
                array_unshift($args, $method);
                $method = $ctl;
                $ctl = array_pop($parr);
            } else {
                throw new \Exception('firegit.u_notfound path=' . $ctlPath);
            }
        }
        $className = implode(
            "\\",
            array_merge(
                array('firegit', 'app', 'ctl'),
                array_map('ucfirst', $parr),
                array(ucfirst($ctl)),
                array('Controller')
            )
        );

        require_once $ctlPath;
        trigger_error('ctlpath:'.$ctlPath, E_USER_NOTICE);
        if (!class_exists($className)) {
            throw new \Exception('firegit.u_notfound cls=' . $className);
        }

        $req = new \firegit\http\Request();
        $req->url = $info['path'];
        $req->uri = $uri;
        $req->method = $_SERVER['REQUEST_METHOD'];
        $req->host = $_SERVER['HTTP_HOST'];

        $res = new \firegit\http\Response();
        $methodExt = '_action';

        $ctl = new $className($req, $res);
        if (!method_exists($ctl, $method.$methodExt)) {
            $passed = false;
            if ($method != 'index') {
                if (method_exists($ctl, 'index'.$methodExt)) {
                    array_unshift($args, $method);
                    $method = 'index';
                    $passed = true;
                }
            }
            if (!$passed) {
                throw new \Exception('firegit.u_notfound method=' . $method);
            }
        }
        $ctl->method = $method;
        if (method_exists($ctl, '_before')) {
            call_user_func(array($ctl, '_before'));
        }
        try {
            call_user_func_array(array($ctl, $method.'_action'), $args);
        } catch (\Exception $ex) {
            // TODO
            print_r($ex->getTraceAsString());
        } finally {
            if (method_exists($ctl, '_after')) {
                call_user_func(array($ctl, '_after'));
            }
        }
    }
}