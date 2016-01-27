<?php
namespace firegit\git;

class Manager
{
    /**
     * 初始化git库
     * @param $group
     * @param $name
     */
    public static function init($group, $name)
    {
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir($gitDir)) {
            mkdir($gitDir, 0755, true);
        }
        chdir($gitDir);
        system('git init --bare');
        system('git config http.receivepack true');
        system('rm hooks/*');

        $phpPath = realpath(dirname(__DIR__).'/vendor/firegit/autoload.php');
        $content = <<<HOOK
#! /usr/local/bin/php
<?php
include '{$phpPath}';

if (!\\firegit\\hook\\Hook::preReceive()) {
    exit(1);
}
HOOK;
        $hookPath = $gitDir.'/hooks/pre-receive';
        file_put_contents($hookPath, $content);
        chmod($hookPath, 0755);

        system('chown git:git '.$gitDir.' -R');
    }

    /**
     * 获取某个分组里边的git库
     * @param $group
     * @return array
     */
    public static function getReposByGroup($group)
    {
        $groupDir = GIT_REPO.'/'.$group;
        exec('ls '.$groupDir.'/*.git -d', $dirs);
        $ret = array();
        foreach($dirs as $dir) {
            $ret[] = array(
                'name' => pathinfo($dir, PATHINFO_FILENAME),
                'dir' => basename($dir),
            );
        }
        return $ret;
    }
}