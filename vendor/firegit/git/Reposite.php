<?php
namespace firegit\git;

use firegit\git\Util;

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
        $this->dir = realpath(GIT_REPO.'/'.$this->group.'/'.$this->name.'.git');
        if (!is_dir($this->dir)) {
            throw new \Exception('reposite.dirNotFound dir='.$this->dir);
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
        exec($cmd, $outputs, $code);
        // 没有找到该分支的任何文件
        if ($code !== 0) {
            $outputs = array();
        }
        $ret = array(
            'dirs' => array(),
            'files' => array(),
        );
        foreach($outputs as $key => $line) {
            $node = self::parseByLine($line);
            if ($node['dir']) {
                $ret['dirs'][strtolower($node['name'])] = $node;
            } else {
                $ret['files'][strtolower($node['name'])] = $node;
            }
        }
        ksort($ret['dirs'], SORT_STRING   );
        ksort($ret['files'], SORT_STRING   );
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
        exec($cmd, $outputs, $code);
        $commits = array();
        foreach($outputs as $line) {
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
        exec($cmd, $outputs, $code);
        $branches = array();
        foreach($outputs as $line) {
            $line = ltrim(ltrim($line, '*'));
            $arr = preg_split('#\s+#', $line, 3);
            $branches[] = array(
                'name' => $arr[0],
                'hash' => $arr[1],
                'msg' => $arr[2],
            );
        }
        return $branches;
    }

    static function  parseByLine($line)
    {
        list($mode, $type, $hash, $size, $path) = preg_split('#[\s\t]+#', $line);
        $path = Util::normalPath($path);

        $node = array(
            'mode' => $mode,
            'type' => $type,
            'hash' => $hash,
            'path' => $path,
            'dir'  => false,
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
}