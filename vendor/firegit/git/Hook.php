<?php
namespace firegit\git;

class Hook
{
    var $group;
    var $name;
    var $fromHttp;
    var $authUser = false;

    private static $hooks = array();
    /**
     * Hook constructor.
     * @param $gitDir git项目的地址
     */
    function __construct($gitDir)
    {
        $this->name = basename($gitDir, '.git');
        $this->group = basename(dirname($gitDir));

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->fromHttp = true;
        }
        if ($this->fromHttp) {
            list($_, $uname) = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            $this->authUser = $uname;
        }
    }

    /**
     * 增加hook
     * @param $hookName
     * @param callable $hook
     * @throws \Exception
     */
    static function addHook($hookName, $hook)
    {
        if (!is_callable($hook)) {
            throw new \Exception('hook.illegalHook');
        }
        self::$hooks[$hookName][] = $hook;
    }

    /**
     * 从远程推送提交到服务器上时
     * @return bool
     */
    function preReceive()
    {
        $commits = array();

        while (!feof(STDIN)) {
            $line = trim(fgets(STDIN));
            if (!$line) {
                continue;
            }
            list($oref, $nref, $branch) = explode(' ', $line);
            $commits[] = array(
                'start' => $oref,
                'end' => $nref,
                'branch' => $branch,
            );
        }

        if (isset(self::$hooks['preReceive'])) {
            foreach(self::$hooks['preReceive'] as $hook) {
                $ret = call_user_func_array($hook, array($this, $commits));
                if ($ret === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 从远程成功推送到服务器上时
     * @return bool
     */
    function postReceive()
    {
        $commits = array();

        while (!feof(STDIN)) {
            $line = trim(fgets(STDIN));
            if (!$line) {
                continue;
            }
            list($oref, $nref, $branch) = explode(' ', $line);
            $commits[] = array(
                'start' => $oref,
                'end' => $nref,
                'branch' => $branch,
            );
        }

        if (isset(self::$hooks['postReceive'])) {
            foreach(self::$hooks['postReceive'] as $hook) {
                call_user_func_array($hook, array($this, $commits));
            }
        }
    }
}
