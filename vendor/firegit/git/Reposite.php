<?php
namespace firegit\git;

use \firegit\git\Util;

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
        foreach ($lines as $key => $line) {
            $node = self::parseByLine($line);
            if ($node['dir']) {
                $ret['dirs'][strtolower($node['name'])] = $node;
            } else {
                $ret['files'][strtolower($node['name'])] = $node;
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
    function listCommits($hash, $num = 40)
    {
        chdir($this->dir);
        $cmd = sprintf('git log --oneline -%d %s --format="%s"', $num, $hash, '%H %ct %an %s');
        exec($cmd, $lines, $code);
        $commits = array();
        foreach ($lines as $line) {
            $arr = explode(' ', $line, 4);
            $commits[] = array(
                'hash' => $arr[0],
                'time' => $arr[1],
                'author' => $arr[2],
                'msg' => isset($arr[3]) ? $arr[3] : '',
            );
        }
        return $commits;
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
        return $branches;
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
        $node = self::parseByLine($lines[0]);

        $cmd = 'git show ' . $node['hash'];
        ob_start();
        system($cmd);
        $node['content'] = ob_get_clean();
        return $node;
    }

    static function parseByLine($line)
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


        if ($type == 'tree') {
            $node['dir'] = true;
        } else {
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
     * 获取一个提交的统计信息
     * @param $hash
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
            'files' => array()
        );
        array_shift($lines);
        $line = array_pop($lines);
        // 2 files changed, 5 insertions(+), 6 deletions(-)
        $arr = array_map('trim', explode(',', $line));
        foreach ($arr as $a) {
            list($num, $desc) = explode(' ', $a, 2);
            switch ($desc) {
                case 'files changed':
                    $ret['total'] = $num;
                    break;
                case 'insertions(+)':
                    $ret['add'] = $num;
                    break;
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

    /**
     * 获取变化
     * @param $commitFrom
     * @param null $commitEnd 如果此参数不提供，则认为获取$commitFrom和上一个提交的变化
     * @return array
     */
    function listDiffs($commitFrom, $commitEnd = null)
    {
        if ($commitEnd === null) {
            $commitEnd = $commitFrom;
            $commitFrom = $commitFrom . '^';
        }
        chdir($this->dir);
        $cmd = sprintf('git diff %s..%s ', $commitFrom, $commitEnd);
        exec($cmd, $lines, $code);
        $diffs = array();
        $diff = null;
        $blocks = null;
        $fromLine = 0;
        $toLine = 0;
        for ($i = 0, $l = count($lines); $i < $l; $i++) {
            $line = $lines[$i];
            if (strpos($line, 'diff --git ') === 0) {
                if ($diff !== null) {
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
                } else {
                    if ($line) {
                        switch ($line[0]) {
                            case '-': // 表示是起始commit的文件
                                $fromLine++;
                                $blocks[] = array(
                                    'from' => $fromLine,
                                    'line' => $line
                                );
                                break;
                            case '+':
                                $toLine++;
                                $blocks[] = array(
                                    'to' => $toLine,
                                    'line' => $line,
                                );
                                break;
                            case '\\':

                                break;
                            default:
                                $fromLine++;
                                $toLine++;
                                $blocks[] = array(
                                    'from' => $fromLine,
                                    'to' => $toLine,
                                    'line' => $line,
                                );
                        }
                    } else {
                        $fromLine++;
                        $toLine++;
                        $blocks[] = array(
                            'from' => $fromLine,
                            'to' => $toLine,
                            'line' => $line,
                        );
                    }
                }
            }
        }
        $diff['blocks'][] = $blocks;
        $diffs[] = $diff;
        return $diffs;
    }
}