<?php
namespace firegit\hook;

require_once dirname(__DIR__).'/util/ColorConsole.php';

class GitHook
{
    /**
     * 从远程推送提交到服务器上时
     * @return bool
     */
    static function preReceive()
    {
        $gitGroup = $_SERVER['FIREGIT_GROUP'];
        $gitName = $_SERVER['FIREGIT_NAME'];

        list($_, $auth) = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);

        $zeroCommit = str_repeat('0', 40);

        while (!feof(STDIN)) {
            $line = trim(fgets(STDIN));
            if (!$line) {
                continue;
            }
            list($oref, $nref, $branch) = explode(' ', $line);

            if ($branch != 'refs/heads/master') {
                // 检查分支是否存在
                system('git show-branch ' . $branch . ' > /dev/null 2>&1', $code);
                if ($code !== 0) {
                    \firegit\util\ColorConsole::error($branch . ' must create from server');
                    return false;
                }
            }

            if ($oref == $zeroCommit) {
                system('git ls-tree -r ' . $nref);
            } else {
                system(sprintf('git diff-tree  %s..%s --stat', $oref, $nref));
            }
        }
        return true;
    }
}
