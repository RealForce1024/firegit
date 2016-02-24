<?php
namespace firegit\app\hook;

use \firegit\git\Hook;
use \firegit\git\Reposite;
use \firegit\git\Util;

class Git
{
    static function init()
    {
        $conf = include CONF_ROOT . '/db.php';
        \firegit\db\Db::init($conf);
        Hook::addHook('preReceive', '\\firegit\\app\\hook\\Git::preReceive');
        Hook::addHook('postReceive', '\\firegit\\app\\hook\\Git::postReceive');
    }

    /**
     * 开始接收数据前
     * @param Hook $hook
     * @param $commits
     * @return bool
     */
    static function preReceive(Hook $hook, $commits)
    {
        $reposite = new Reposite($hook->group, $hook->name);

        // TODO检查用户是否能操作该库

        // 检查每个分支是否已经创建过
        foreach ($commits as $commit) {
            $branch = $commit['branch'];

            // TODO 检查用户是否能操作该分支

            if (strpos($branch, Util::TAG_PREFIX) !== 0) {
                // TODO：是否为强制更新

                // 如果不是主分支，且服务器端未创建，则不允许提交
                if ($branch != Util::normalBranch('master')) {
                    if (!$reposite->isBranchExists($branch)) {
                        \firegit\util\ColorConsole::error($branch . ' must create from server');
                        return false;
                    }
                }
            }
        }
    }

    /**
     * 数据提交完毕后
     * @param Hook $hook
     * @param $commits
     */
    static function postReceive(Hook $hook, $commits)
    {
        $reposite = new Reposite($hook->group, $hook->name);

        // 检查每个分支是否已经创建过
        $merges = array();
        $branches = array();
        foreach ($commits as $commit) {
            $branch = $commit['branch'];
            if (strpos($branch, Util::TAG_PREFIX) !== 0) {
                $_ms = $reposite->listMergeCommits($commit['start'], $commit['end']);
                if ($_ms) {
                    $merges = array_merge($merges, $_ms);
                }

                $branches[substr($branch, strlen(Util::BRANCH_PREFIX))] = $commit['end'];
            }
        }

        $merge = new \firegit\app\mod\git\Merge();
        if ($merges) {
            $merge->handleMerges($hook->group, $hook->name, $merges);
        }

        if ($branches) {
            $merge->updateBranches($hook->group, $hook->name, $branches);
        }
    }

}