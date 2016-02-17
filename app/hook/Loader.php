<?php
namespace firegit\app\hook;

use \firegit\git\Hook;
use \firegit\git\Util;

class Loader
{
    static function init()
    {
        $conf = include CONF_ROOT.'/db.php';
        \firegit\db\Db::init($conf);
        Hook::addHook('preReceive', '\\firegit\\app\\hook\\Loader::preReceive');
    }

    static function preReceive(Hook $hook, $commits)
    {
        $reposite = new \firegit\git\Reposite($hook->group, $hook->name);

        // TODO检查用户是否能操作该库

        // 检查每个分支是否已经创建过
        $merges = array();
        foreach($commits as $commit) {
            $branch = $commit['branch'];
            if (strpos($branch, Util::TAG_PREFIX) !== 0) {
                // 如果不是主分支，且服务器端未创建，则不允许提交
                if ($branch != Util::normalBranch('master')) {
                    if (!$reposite->isBranchExists($branch)) {
                        \firegit\util\ColorConsole::error($branch . ' must create from server');
                        return false;
                    }
                }
                $_ms = $reposite->listMergeCommits($commit['start'], $commit['end']);
                if ($_ms) {
                    $merges = array_merge($merges, $_ms);
                }
            }
        }

        $merge = new \firegit\app\mod\git\Merge();
        $merge->handleMerges($hook->group, $hook->name, $merges);
    }
}