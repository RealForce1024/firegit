<?php
namespace firegit\app;

class Bootstrap
{
    public static function start()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', false);

        self::initUrl();

        self::initDb();

        \firegit\http\Dispatcher::dispatch();
    }

    /**
     * 初始化网址
     */
    public static function initUrl()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $_SERVER['REQUEST_RAWURI'] = $uri;
        if (preg_match('#^\/([a-zA-Z][a-zA-Z0-9\-\_]{2,19})(?:\/([a-zA-Z][a-zA-Z0-9\-\_]+)(\.git)?(\/.*)?)?$#', $uri, $ms)) {
            if (!isset($ms[2])) {
                $dir = GIT_REPO.$ms[1];
                if (is_dir($dir)) {
                    $_SERVER['REQUEST_URI'] = '/group/'.$ms[1];
                    return;
                }
            }
            $dir = GIT_REPO . $ms[1] . '/' . $ms[2] . '.git';

            if (is_dir($dir)) {
                $_SERVER['GIT_GROUP'] = $ms[1];
                $_SERVER['GIT_NAME'] = $ms[2];

                // 带有git的自动跳转
                if (isset($ms[3]) && $ms[3] == '.git') {
                    header('Location: /' . $ms[1] . '/' . $ms[2] . (isset($ms[4]) ? $ms[4] : ''));
                    exit();
                }
                $arr = explode('/', $uri);
                $_SERVER['REQUEST_URI'] = '/git/'.implode('/', array_slice($arr, 3));
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
