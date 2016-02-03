<?php
namespace firegit\git;

class Manager
{
    /**
     * 执行任务
     * @param string $task 任务名称，
     *  * init 初始化
     *  * newBranch 创建分支
     *  * delBranch 删除分支
     * @param $group
     * @param $name
     * @param array $args
     */
    public static function doTask($task, $group, $name, array $args = array())
    {
        $argStrs = array();
        foreach ($args as $key => $value) {
            $argStrs[] = '-d ' . $key . ':' . $value;
        }
        $cmd = sprintf('%s php %s --group %s --name %s --action %s %s',
            BIN_ROOT . '/chother',
            BIN_ROOT . '/GitManager.php',
            $group,
            $name,
            $task,
            $argStrs ? implode(' ', $argStrs) : ''
        );
        file_put_contents(LOG_ROOT . 'task', $cmd);
        exec($cmd, $outputs, $code);
        return $code    ;
    }

    /**
     * 初始化git库
     * @param $group
     * @param $name
     */
    public function init($group, $name)
    {
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir(GIT_REPO . $group)) {
            system(sprintf('mkdir -p %s && chown %s:%s %s -R', $gitDir, GIT_USER, GIT_GROUP, GIT_REPO . $group));
        }
        chdir($gitDir);
        system('git init --bare');
        system('git config http.receivepack true');
        system('rm hooks/*');

        $phpPath = realpath(dirname(__DIR__) . '/autoload.php');
        $content = <<<HOOK
#! /usr/local/bin/php
<?php
include '{$phpPath}';

if (!\\firegit\\git\\Hook::preReceive()) {
    exit(1);
}
HOOK;
        $hookPath = $gitDir . '/hooks/pre-receive';
        file_put_contents($hookPath, $content);
        chmod($hookPath, 0755);

        system(sprintf('chown %s:%s %s -R', GIT_USER, GIT_GROUP, $gitDir));
    }

    /**
     * 获取某个分组里边的git库
     * @param $group
     * @return array
     */
    public static function getReposByGroup($group)
    {
        $groupDir = GIT_REPO . '/' . $group;
        exec('ls ' . $groupDir . '/*.git -d', $dirs);
        $ret = array();
        foreach ($dirs as $dir) {
            $ret[] = array(
                'name' => pathinfo($dir, PATHINFO_FILENAME),
                'dir' => basename($dir),
            );
        }
        return $ret;
    }

    /**
     * 创建服务器端分支
     * @param $group
     * @param $name
     * @param $orig
     * @param $dest
     * @return int 返回状态值
     *  * 0 成功
     *  * 1 git仓库不存在
     *  * 2 $orig分支不存在
     *  * 3 $dest分支已经存在
     */
    public function newBranch($group, $name, $orig, $dest)
    {
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir($gitDir)) {
            return 1;
        }
        chdir($gitDir);
        $cmd = sprintf('su git -c "git branch %s %s"', Util::normalBranch($dest), $orig);
        exec($cmd, $outputs, $code);
        return $code;
    }

    /**
     * 删除分支
     * @param $group
     * @param $name
     * @param $branch
     * @return int
     */
    public function delBranch($group, $name, $branch)
    {
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir($gitDir)) {
            return 1;
        }
        chdir($gitDir);
        $cmd = sprintf('su git -c "git branch -d %s"', $branch);
        file_put_contents(LOG_ROOT . 'cmd', $cmd);
        exec($cmd, $outputs, $code);
        return $code;
    }

    /**
     * 打标签
     */
    public function addTag($group, $name, $orig, $tagname){
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir($gitDir)) {
            return 1;
        }
        chdir($gitDir);
        $cmd = sprintf('su git -c "git tag %s %s"', $tagname, $orig);
        file_put_contents(LOG_ROOT . 'cmd', $cmd);
        exec($cmd, $outputs, $code);
        return $code;
    }
}