<?php
namespace firegit\git;

define('ZERO_COMMIT', str_repeat('0', 40));

class Reposite
{
    var $group;
    var $name;
    var $dir;

    /**
     * Reposite constructor.
     * @param string $group 组名
     * @param string $name 库名
     * @throws \Exception
     */
    function __construct($group, $name)
    {
        $this->group = $group;
        $this->name = $name;
        $this->dir = realpath(GIT_REPO . '/' . $this->group . '/' . $this->name . '.git');
        if (!is_dir($this->dir)) {
            throw new \Exception('reposite.dirNotFound dir=' . $this->dir);
        }
    }

    /**
     * 创建服务器端分支
     * @param $orig
     * @param $dest
     * @return int 返回状态值
     *  * 0 成功
     *  * 1 git仓库不存在
     *  * 2 $orig分支不存在
     *  * 3 $dest分支已经存在
     */
    function newBranch($orig, $dest)
    {
        chdir($this->dir);
        $cmd = sprintf('git branch %s %s', $dest, Util::normalBranch($orig));
        exec($cmd, $lines, $code);
        return $code;
    }


    /**
     * 删除分支
     * @param $branch
     * @return int
     */
    public function delBranch($branch)
    {
        chdir($this->dir);
        $cmd = sprintf('git branch -D %s', $branch);
        exec($cmd, $lines, $code);
        return $code;
    }

    /**
     * 显示分支的文件目录
     * @param $branch
     * @param $dir
     * @return array
     */
    function listFiles($branch, $dir)
    {
        chdir($this->dir);
        $cmd = sprintf('git ls-tree %s "%s" -l', $branch, $dir);
        exec($cmd, $lines, $code);
        // 没有找到该分支的任何文件
        if ($code !== 0) {
            $lines = array();
        }
        $ret = array(
            'dirs' => array(),
            'files' => array(),
        );
        $modules = null;
        foreach ($lines as $key => $line) {
            $node = $this->parseLsLine($line);
            switch ($node['type']) {
                case 'commit':
                    if ($modules === null) {
                        $modules = $this->listModules($branch);
                    }
                    if (isset($modules[$node['path']])) {
                        $node['url'] = $modules[$node['path']]['url'];
                    }
                case 'tree':
                    $ret['dirs'][strtolower($node['name'])] = $node;
                    break;
                case 'blob':
                    $ret['files'][strtolower($node['name'])] = $node;
                    break;
            }
        }
        ksort($ret['dirs'], SORT_STRING);
        ksort($ret['files'], SORT_STRING);
        return $ret;
    }

    /**
     * 获取分支的提交
     * @param $hash
     * @param int $num
     * @return array
     */
    function pagedGetCommits($hash, $num = 40)
    {
        chdir($this->dir);
        $cmd = sprintf('git log --oneline -%d %s --format="%s"', $num + 1, $hash, '%H %ct %an %s');
        exec($cmd, $lines, $code);
        $commits = array();
        $next = null;
        if (count($lines) > $num) {
            $next = explode(' ', array_pop($lines), 2);
        }
        $commits = self::parseCommitsLines($lines);
        return array(
            'commits' => $commits,
            'next' => $next ? $next[0] : 0
        );
    }

    /**
     * 解析commit的行
     * @param $lines
     * @return array
     */
    private function parseCommitsLines($lines)
    {
        $commits = array();
        foreach ($lines as $line) {
            $arr = explode(' ', $line, 4);
            $commits[] = array(
                'time' => $arr[1],
                'hash' => $arr[0],
                'author' => $arr[2],
                'msg' => isset($arr[3]) ? $arr[3] : '',
            );
        }
        return $commits;
    }

    /**
     * 列出两个commit中间的提交
     * @param $hashStart
     * @param $hashEnd
     * @return array
     */
    function listCommits($hashStart, $hashEnd)
    {
        chdir($this->dir);
        $cmd = sprintf('git log --oneline --format="%s" %s..%s', '%H %ct %an %s', $hashStart, $hashEnd);
        exec($cmd, $lines, $code);
        return self::parseCommitsLines($lines);
    }

    /**
     * 获取分支
     */
    function listBranches()
    {
        chdir($this->dir);
        $cmd = sprintf('git branch -v --list');
        exec($cmd, $lines, $code);
        $branches = array();
        foreach ($lines as $line) {
            $line = ltrim(ltrim($line, '*'));
            $arr = preg_split('#\s+#', $line, 3);

            $branch = array(
                'name' => $arr[0],
                'hash' => $arr[1],
                'msg' => $arr[2],
            );
            if ($arr[0] == 'master') {
                array_unshift($branches, $branch);
            } else {
                $branches[] = $branch;
            }
        }
        return array_column($branches, null, 'name');
    }

    /**
     * 获取标签
     */
    function listTags()
    {
        chdir($this->dir);
        $cmd = sprintf('git tag -n');
        exec($cmd, $lines, $code);
        if (!empty($lines)) {
            //TODO
        }
        return $lines;
    }

    /**
     * 获取文件原始内容
     * @param $branch
     * @param $path
     * @return string
     */
    function getBlob($branch, $path)
    {
        chdir($this->dir);
        $cmd = sprintf('git ls-tree %s %s -l', $branch, $path);
        exec($cmd, $lines, $code);
        if (!$lines) {
            return false;
        }
        $node = $this->parseLsLine($lines[0]);

        $cmd = 'git show ' . $node['hash'];
        ob_start();
        system($cmd);
        $node['content'] = ob_get_clean();
        return $node;
    }

    private $modules;

    /**
     * 获取所有子模块
     * @param string $branch
     * @return array
     */
    function listModules($branch)
    {
        if ($this->modules) {
            return $this->modules;
        }
        $node = $this->getBlob($branch, '.gitmodules');
        if (!$node) {
            return array();
        }
        $lines = explode("\n", $node['content']);
        $modules = array();
        $module = array();
        foreach ($lines as $line) {
            if (strncmp($line, '[submodule ', 11) === 0) {
                if (!empty($module)) {
                    $modules[$module['name']] = $module;
                    $module = array();
                }
                $module['name'] = trim(substr($line, 11, -1), '"\'');
            } else {
                $line = trim($line);
                $arr = array_map('trim', explode('=', $line, 2));
                if (count($arr) == 2) {
                    if ($arr[0] == 'path') {
                        $module['path'] = $arr[1];
                    } else {
                        if ($arr[0] == 'url') {
                            $module['url'] = $arr[1];
                        }
                    }
                }
            }
        }
        if (!empty($module)) {
            $modules[$module['name']] = $module;
        }
        return $this->modules = $modules;
    }

    /**
     * 解析ls-tree产生的一行
     * @param $line
     * @return array
     */
    function parseLsLine($line)
    {
        list($mode, $type, $hash, $size, $path) = preg_split('#[\s\t]+#', $line);
        $path = Util::normalPath($path);

        $node = array(
            'mode' => $mode,
            'type' => $type,
            'hash' => $hash,
            'path' => $path,
            'dir' => false,
        );


        if ($type == 'blob') {
            $pos = strrpos($path, '.');
            if ($pos !== false) {
                $node['ext'] = substr($path, $pos + 1);
            } else {
                $node['ext'] = '';
            }

            $node['size'] = Util::smartSize($size);
        }
        $pos = strrpos($path, '/');
        if ($pos !== false) {
            $node['name'] = substr($path, $pos + 1);
        } else {
            $node['name'] = $path;
        }

        return $node;
    }

    /**
     * 检查分支是否存在
     * @param $branch
     * @return bool
     */
    function isBranchExists($branch)
    {
        chdir($this->dir);
        system('git show-branch ' . $branch . ' > /dev/null 2>&1', $code);
        return $code === 0;
    }


    /**
     * 获取合并的提交
     * @param $fromHash
     * @param $endHash
     * @return array
     */
    function listMergeCommits($fromHash, $endHash)
    {
        chdir($this->dir);
        $cmd = sprintf('git log --merges --pretty=raw -1 %s%s', $fromHash == ZERO_COMMIT ? '' : $fromHash . '..',
            $endHash);
        exec($cmd, $lines, $code);
        $ret = array();
        while (true) {
            $line = array_shift($lines);
            if ($line === null) {
                break;
            }
            if (substr($line, 0, 7) == 'commit ') {
                $commit = array();
                $commit['hash'] = substr($line, 7);
                $commit['tree'] = substr(array_shift($lines), strlen('tree '));
                $commit['dest'] = substr(array_shift($lines), strlen('parent '));
                $commit['orig'] = substr(array_shift($lines), strlen('parent '));
                $ret[] = $commit;
            }
        }
        return $ret;
    }

    /**
     * 获取一个提交的统计信息
     * @param $hash
     * @return array
     */
    function statCommit($hash)
    {
        chdir($this->dir);
        $cmd = sprintf('git show %s  --stat --oneline --format="%s"', $hash, '%h %ct %an %s');
        exec($cmd, $lines, $code);
        if ($code) {
            return false;
        }
        $line = array_shift($lines);
        $arr = explode(' ', $line);
        $ret = array(
            'hash' => $arr[0],
            'time' => $arr[1],
            'author' => $arr[2],
            'msg' => isset($arr[3]) ? $arr[3] : '',
            'files' => array(),
            'total' => 0,
            'add' => 0,
            'delete' => 0
        );
        $info = self::parseDiffStatLines($lines);
        foreach ($info as $k => $v) {
            $ret[$k] = $v;
            array_shift($lines);
            $line = array_pop($lines);
            // 2 files changed, 5 insertions(+), 6 deletions(-)
            $arr = array_map('trim', explode(',', $line));
            foreach ($arr as $a) {
                list($num, $desc) = explode(' ', $a, 2);
                switch ($desc) {
                    case 'file changed':
                        $ret['total'] = $num;
                        break;
                    case 'insertion(+)':
                    case 'insertions(+)':
                        $ret['add'] = $num;
                        break;
                    case 'deletion(-)':
                    case 'deletions(-)':
                        $ret['delete'] = $num;
                        break;
                }
            }
            foreach ($lines as $line) {
                list($file, $stat) = array_map('trim', explode('|', $line, 2));
                list($num, $changes) = explode(' ', $stat, 2);
                if ($num == 'Bin') {
                    list($from, $to) = explode(' -> ', $changes, 2);
                    $ret['files'][$file] = array(
                        'type' => 'bin',
                        'from' => $from,
                        'to' => $to,
                    );
                } else {
                    $stats = count_chars($changes, 1);
                    $ret['files'][$file] = array(
                        'type' => 'text',
                        'total' => $num,
                        'add' => isset($stats[43]) ? $stats[43] : 0,
                        'delete' => isset($stats[45]) ? $stats[45] : 0,
                    );
                }
            }
            return $ret;
        }
    }

    /**
     * 获取变化
     * @param $commitFrom
     * @param null $commitEnd 如果此参数不提供，则认为获取$commitFrom和上一个提交的变化
     * @param string $path
     * @return array
     */
    function listDiffs($commitFrom, $commitEnd = null, $path = null)
    {
        if ($commitEnd === null) {
            $commitEnd = $commitFrom;
            $commitFrom = $commitFrom . '^';
        }
        chdir($this->dir);
        $cmd = sprintf('git diff %s..%s %s', $commitFrom, $commitEnd, $path !== null ? ' -- ./'.$path : '');
        exec($cmd, $lines, $code);
        $diffs = array();
        $diff = null;
        $blocks = null;
        $fromLine = 0;
        $toLine = 0;
        $incrLine = true;
        for ($i = 0, $l = count($lines); $i < $l; $i++) {
            $line = $lines[$i];
            if (strpos($line, 'diff --git ') === 0) {
                if ($diff) {
                    if ($blocks) {
                        $diff['blocks'][] = $blocks;
                        $blocks = array();
                    }
                    $diffs[] = $diff;
                }

                $diff = array(
                    'from' => array(),
                    'to' => array(),
                    'blocks' => array(),
                );
                $arr = explode(' ', $line);
                $diff['from']['path'] = substr($arr[2], 1);
                $diff['to']['path'] = substr($arr[3], 1);

                // 查找下一行
                $i++;
                $line = $lines[$i];
                if (strncmp($line, 'index', 5) !== 0) {
                    $i++;
                    $line = $lines[$i];
                }
                $arr = preg_split('#(\s|\.\.)#', $line);
                $diff['from']['hash'] = $arr[1];
                $diff['to']['hash'] = $arr[2];

                // 跨越两行
                $i++;
                $line = $lines[$i];
                if (strncmp($line, 'Binary', 6) === 0) {
                    $diff['type'] = 'bin';
                } else {
                    $diff['type'] = 'file';
                    $i++;
                }
            } else {
                if (strpos($line, '@@ -') === 0) {
                    if ($blocks) {
                        $diff['blocks'][] = $blocks;
                    }
                    $blocks = array();

                    $arr = explode(' ', $line, 5);
                    list($fromLine) = explode(',', substr($arr[1], 1));
                    list($toLine) = explode(',', substr($arr[2], 1));
                    $blocks[] = array(
                        'from' => $fromLine,
                        'to' => $toLine,
                        'line' => $line,
                    );
                    if (substr($line, -3) == ' @@') {
                        $incrLine = false;
                    }
                } else {
                    if ($line) {
                        switch ($line[0]) {
                            case '-': // 表示是起始commit的文件
                                if (!$incrLine) {
                                    $incrLine = true;
                                } else {
                                    $fromLine++;
                                }
                                $blocks[] = array(
                                    'from' => $fromLine,
                                    'line' => $line
                                );
                                break;
                            case '+':
                                if (!$incrLine) {
                                    $incrLine = true;
                                } else {
                                    $toLine++;
                                }
                                $blocks[] = array(
                                    'to' => $toLine,
                                    'line' => $line,
                                );
                                break;
                            case '\\':
                                $blocks[] = array(
                                    'line' => $line,
                                );
                                break;
                            default:
                                if (!$incrLine) {
                                    $incrLine = true;
                                } else {
                                    $fromLine++;
                                    $toLine++;
                                }

                                $blocks[] = array(
                                    'from' => $fromLine,
                                    'to' => $toLine,
                                    'line' => $line,
                                );
                        }
                    } else {
                        if (!$incrLine) {
                            $incrLine = true;
                        } else {
                            $fromLine++;
                            $toLine++;
                        }
                        $blocks[] = array(
                            'from' => $fromLine,
                            'to' => $toLine,
                            'line' => $line,
                        );
                    }
                }
            }
        }
        if (!empty($blocks)) {
            $diff['blocks'][] = $blocks;
        }
        if (!empty($diff)) {
            $diffs[] = $diff;
        }
        return $diffs;
    }

    /**
     * 统计变化
     * @param $commitFrom
     * @param null $commitEnd
     * @return array
     */
    function statDiffs($commitFrom, $commitEnd = null)
    {
        if ($commitEnd === null) {
            $commitEnd = $commitFrom;
            $commitFrom = $commitFrom . '^';
        }
        chdir($this->dir);
        $cmd = sprintf('git diff %s..%s --stat', $commitFrom, $commitEnd);
        exec($cmd, $lines, $code);
        return self::parseDiffStatLines($lines);
    }

    private static function parseDiffStatLines($lines)
    {
        $line = array_pop($lines);
        // 2 files changed, 5 insertions(+), 6 deletions(-)
        $arr = array_map('trim', explode(',', $line));
        $ret = array(
            'total' => 0,
            'delete' => 0,
            'add' => 0,
        );
        foreach ($arr as $a) {
            list($num, $desc) = explode(' ', $a, 2);
            switch ($desc) {
                case 'files changed':
                    $ret['total'] = $num;
                    break;
                case 'insertions(+)':
                case 'insertion(+)':
                    $ret['add'] = $num;
                    break;
                case 'deletions(-)':
                case 'deletion(-)':
                    $ret['delete'] = $num;
                    break;
            }
        }
        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            list($file, $stat) = array_map('trim', explode('|', $line, 2));
            list($num, $changes) = explode(' ', $stat, 2);
            if ($num == 'Bin') {
                list($from, $to) = explode(' -> ', $changes, 2);
                $ret['files'][$file] = array(
                    'type' => 'bin',
                    'from' => $from,
                    'to' => $to,
                );
            } else {
                $stats = count_chars($changes, 1);
                $ret['files'][$file] = array(
                    'type' => 'text',
                    'total' => $num,
                    'add' => isset($stats[43]) ? $stats[43] : 0,
                    'delete' => isset($stats[45]) ? $stats[45] : 0,
                );
            }
        }
        return $ret;
    }

    /**
     * 获取分支对应的hash值
     * @param $branch
     * @return bool|string
     */
    function getBranchHash($branch)
    {
        chdir($this->dir);
        $hash = exec('git show-ref -s ' . Util::normalBranch($branch), $lines, $code);
        if ($lines) {
            return $lines[0];
        }
        return false;
    }

    /**
     * 文件追责
     * @param $branch
     * @param $path
     * @return string
     */
    function getBlame($path)
    {
        chdir($this->dir);

        $arr = null;
        $log = $this->getHistory($path);
        $log = $log['commits'];

        $firstHash = $log[0]['hash'];
        exec(sprintf('git ls-tree %s %s', $firstHash, './'.$path), $outputs);

        exec('git show c1d496b32886180f66a2c14968bec55340e5344c', $content);

//        asort($log);
        foreach ( $log as $log_key=>$log_val) {
            $diff = $this->listDiffs($log_val['hash'], null, $path);
            foreach($diff['blocks'] as $arr_key=>$arr_val){
                foreach($arr_val as $val_key=>$val_val){
                    if (strpos($val_val['line'], '+') === 0) {
                        --$val_val['to'];
                        $arr[$val_val['to']]['code'] = ltrim($val_val['line'], '+');
                        $arr[$val_val['to']]['hash'] = $val_val['hash'];
                        $arr[$val_val['to']]['author'] = $val_val['author'];
                        $arr[$val_val['to']]['msg'] = $val_val['msg'];
                        $arr[$val_val['to']]['line'] = $val_val['to'];
//                    }elseif(strpos($val_val['line'], '-') === 0){
//                        print_r($diff['blocks']);die;
//                        return $val_val;
//                        --$val_val['from'];
//                        unset($arr[$val_val['from']]);
//                        //return $val_val;
                    }
                }

            }

        }
        ksort($arr);
        return $arr;
    }

    /**
     * 代码历史
     */
    function getHistory($path)
    {
        chdir($this->dir);
        $cmd = sprintf('git log --oneline --format="%s" -- %s', '%ct %H %an %s %ce', $path);
        exec($cmd, $lines, $code);
        $commits = self::parseCommitsLines($lines);
        return array(
            'commits' => $commits,
        );
    }

}
