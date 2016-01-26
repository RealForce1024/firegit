<?php
namespace firegit\git;

const GIT_REPO = '/home/git/repos/';
class Manager
{
    function init($group, $name)
    {
        $gitDir = GIT_REPO . $group . '/' . $name . '.git';
        if (!is_dir($gitDir)) {
            mkdir($gitDir, 0755, true);
        }
        chdir($gitDir);
        system('git init --bare');
        system('git config http.receivepack true');
        system('rm hooks/*');

        $phpPath = realpath(dirname(__DIR__).'/vendor/firegit/git/Hook.php');
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
}