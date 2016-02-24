<?php
namespace firegit\app;

use \firegit\http\Dispatcher;
use \firegit\http\Controller;

class Bootstrap
{
    public static function start()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', false);

        self::initDb();

        // 网址的hook
        Dispatcher::addHook(Dispatcher::HOOK_NAME_REQUEST, '\firegit\app\BootStrap::initUrl');

        Dispatcher::addHook(Dispatcher::HOOK_NAME_BEFORE, '\firegit\app\BootStrap::initUser');


        \firegit\http\Response::setErrMap(array(
            'firegit.u_login' => '/user/login?u=[u]',
            'firegit.u_notfound' => '/err/notfound',
        ));

        \firegit\http\Dispatcher::dispatch();
    }

    /**
     * 初始化用户
     */
    public static function initUser(\firegit\http\Request $req, \firegit\http\Response $res)
    {
        if (strpos($req->url, '/user/') === 0) {
            return;
        }
        $user = self::checkUser();
        if (!$user) {
            throw new \Exception('firegit.u_login');
        }
        $req->setData('user', $user);
    }
    /**
     * 检查用户是否可以访问
     * @return string|bool
     */
    private static function checkUser()
    {
        if (!isset($_COOKIE['fuser'])) {
            return false;
        }
        $user = new \firegit\app\mod\user\Grant();
        $info = $user->getUserFromCookie($_COOKIE['fuser']);
        if ($info && isset($info['username'])) {
            return $info['username'];
        }

        return false;
    }


    /**
     * 初始化网址
     */
    public static function initUrl(\firegit\http\Request $req)
    {
        $url = $req->url;
        if (preg_match('#^\/([a-zA-Z][a-zA-Z0-9\-\_]{2,19})(?:\/([a-zA-Z][a-zA-Z0-9\-\_]+)(\.git)?(\/.*)?)?$#', $url, $ms)) {
            if (!isset($ms[2])) {
                $dir = GIT_REPO.$ms[1];
                if (is_dir($dir)) {
                    $req->url = '/group/'.$ms[1];
                }
                return;
            }
            $dir = GIT_REPO . $ms[1] . '/' . $ms[2] . '.git';

            if (is_dir($dir)) {
                $req->setData('gitGroup', $ms[1]);
                $req->setData('gitName', $ms[2]);

                // 带有git的自动跳转
                if (isset($ms[3]) && $ms[3] == '.git') {
                    header('Location: /' . $ms[1] . '/' . $ms[2] . (isset($ms[4]) ? $ms[4] : ''));
                    exit();
                }
                $arr = explode('/', $url);
                $req->url = '/git/'.implode('/', array_slice($arr, 3));
            }
        }
    }

    /**
     * 初始化数据库
     */
    public static function initDb()
    {
        $conf = include CONF_ROOT.'/db.php';
        \firegit\db\Db::init($conf);
    }
}
