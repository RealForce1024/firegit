<?php
namespace firegit\http;


class Request
{
    var $uri;
    var $url;
    var $method;
    var $host;

    /**
     * 分配网址
     */
    public static function dispatch()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $info = parse_url($uri);
        $path = $info['path'];
        $path = trim('/', $path);
        $parr = array_diff(explode('/', $path), array(''));

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
        $method .= '_action';
        $ctlPath = CTL_ROOT . implode(PATH_SEPARATOR, $parr) . '/' . ucfirst($ctl) . '.php';
        $className = implode(
            "\\",
            array_merge(
                array('firegit', 'ctl'),
                array_map('ucfirst', $parr),
                array(ucfirst($ctl))
            )
        );
        if (!is_readable($ctlPath)) {
            throw new \Exception('firegit.u_notfound path=' . $ctlPath);
        }

        require_once __DIR__.'/Controller.php';
        require_once $ctlPath;
        if (!class_exists($className)) {
            throw new \Exception('firegit.u_notfound cls=' . $className);
        }

        $req = new self();
        $req->url = $info['path'];
        $req->uri = $uri;
        $req->method = $_SERVER['REQUEST_METHOD'];
        $req->host = $_SERVER['HTTP_HOST'];

        require_once __DIR__ . '/Response.php';
        $res = new \firegit\http\Response();
        
        $ctl = new $className($req, $res);
        if (!method_exists($ctl, $method)) {
            throw new \Exception('firegit.u_notfound method=' . $method);
        }
        call_user_func_array(array($ctl, $method), $args);
    }
}