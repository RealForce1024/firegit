<?php
namespace firegit\http;

class Dispatcher
{
    private static $req;
    private static $res;

    /**
     * 分配网址
     */
    public static function dispatch()
    {
        set_exception_handler("\\firegit\\http\\Dispatcher::onException");
        set_error_handler("\\firegit\\http\\Dispatcher::onError");

        self::$req = $req = new Request();
        self::$res = $res = new Response();

        $path = $req->url;
        $path = trim($path, '/');

        $parr = array_diff(explode('/', $path), array('', '.', '..'));

        $ctl = array_shift($parr);
        if (!$ctl) {
            $ctl = 'index';
        }
        if (!preg_match('#^[a-z][a-z0-9]+$#', $ctl)) {
            throw new \Exception('dispatcher.illegalCtl ctl='.$ctl);
        }
        $args = array();
        $method = '';
        foreach($parr as $key) {
            if ($method || preg_match('#^([1-9][0-9]*)|([a-z0-9]{20,})$#', $key)) {
                $args[] = $key;
            } else {
                $method = $key;
            }
        }
        if (!$method) {
            $method = 'index';
        }

        $ctlPath = CTL_ROOT . strtolower($ctl). '/Controller.php';
        if (!is_readable($ctlPath)) {
            throw new \Exception('firegit.u_notfound path=' . $ctlPath);
        }
        $className = implode(
            "\\",
            array('firegit', 'app', 'ctl', ucfirst($ctl), 'Controller')
        );

        require_once $ctlPath;
        trigger_error('ctlpath:' . $ctlPath, E_USER_NOTICE);
        if (!class_exists($className)) {
            throw new \Exception('firegit.u_notfound cls=' . $className);
        }

        $methodExt = '_action';

        $ctl = new $className($req, $res);
        if (!method_exists($ctl, $method . $methodExt)) {
            $passed = false;
            if ($method != 'index') {
                if (method_exists($ctl, 'index' . $methodExt)) {
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
        call_user_func_array(array($ctl, $method . '_action'), $args);
        if (method_exists($ctl, '_after')) {
            call_user_func(array($ctl, '_after'));
        }

        $res->output($req);
    }

    /**
     * 当出现异常时
     * @param $ex
     */
    public static function onException($ex)
    {
        if (self::$res) {
            self::$res->setException($ex)->output(self::$req);
        } else {
            print_r($ex->getTraceAsString());
        }
    }

    /**
     * 当出现错误时
     */
    public static function onError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        switch ($errno) {
            case E_USER_ERROR:
                echo "<b>My ERROR</b> [$errno] $errstr<br />\n";
                echo "  Fatal error on line $errline in file $errfile";
                echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
                echo "Aborting...<br />\n";
                exit(1);
                break;

            case E_USER_WARNING:
                echo "<b>My WARNING</b> [$errno] $errstr in {$errfile}[line {$errline}]<br />\n";
                break;

            case E_USER_NOTICE:
                return true;
                echo "<b>My NOTICE</b> [$errno] $errstr in {$errfile}[line {$errline}]<br />\n";
                break;

            default:
                echo "Unknown error type: [$errno] $errstr in {$errfile}[line {$errline}]<br />\n";
                break;
        }

        /* Don't execute PHP internal error handler */
        return true;
    }
}