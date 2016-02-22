<?php
namespace firegit\git;

class Util
{
    const BRANCH_PREFIX = 'refs/heads/';
    const TAG_PREFIX = 'refs/tags/';
    /**
     * 获取正常的分支名称
     * @param $branch
     * @return string
     */
    public static function normalBranch($branch)
    {
        if (preg_match('#^[a-z0-9]{40}$#', $branch) ) {
            return $branch;
        }
        return self::BRANCH_PREFIX.$branch;
    }

    /**
     * 获取正常的标签名称
     * @param $tag
     * @return string
     */
    public static function normalTag($tag)
    {
        return self::TAG_PREFIX.$tag;
    }

    /**
     * 将git的路径调整为正常的路径
     * @param $path
     * @return string
     */
    public static function normalPath($path)
    {
        if (preg_match('#^"(.+)"$#', $path, $matches)) {
            $path = $matches[1];
            $path = preg_replace_callback('#\\\[0-7]{3}#', function ($ms) {
                $number = base_convert(intval(substr($ms[0], 1)), 8, 16);
                return '%' . strtoupper($number);
            }, $matches[1]);
            $path = rawurldecode($path);
        }
        return $path;
    }

    /**
     * 将文件大小已智能化方式显示
     * @param $size
     * @return string
     */
    public static function smartSize($size)
    {
        if ($size > 1000 * 1024) {
            $size = sprintf('%.2fM', $size / (1024 * 1024));
        } elseif ($size > 1024) {
            $size = sprintf('%.2fk', $size / 1024);
        } else {
            $size = $size . 'b';
        }
        return $size;
    }

    /**
     * 获取原始文件
     * @param $hash
     * @return string
     */
    public static function getRawFile($hash)
    {
        return system('git show ' . $hash);
    }

    /**
     * 获取两个版本的文件变化
     * @param $oldrev
     * @param $newrev
     * @return array
     */
    public static function getDiffFiles($oldrev, $newrev)
    {
        if ($oldrev == str_repeat('0', 40)) {
            $cmd = "git ls-tree -rl {$newrev}";
            exec($cmd, $outputs);
            $results = array();
            foreach ($outputs as $line) {
                list($newMod, $type, $newHash, $size, $path) = preg_split('#[\t\s]+#', $line);

                $results['A'][] = array(
                    'path' => self::normalPath($path),
                    'hash' => $newHash,
                );
            }
        } else {
            $cmd = "git diff-tree {$oldrev}..{$newrev}";
            exec($cmd, $outputs);
            $results = array();
            foreach ($outputs as $line) {
                list($oldMod, $newMod, $oldHash, $newHash, $type, $path) = preg_split('#[\t\s]+#', $line);
                $results[$type][] = array(
                    'path' => self::normalPath($path),
                    'hash' => $newHash,
                );
            }
        }
        return $results;
    }

    /**
     * 获取文件的扩展名
     * @param $path
     * @return bool|string
     */
    public static function getExt($path)
    {
        if (($pos = strrpos($path, '.')) !== false) {
            return substr($path, $pos + 1);
        }
        return false;
    }

    /**
     * 读取标准输入
     * @return string
     */
    public static function stdin()
    {
        return fgets(STDIN);
    }

    /**
     * 标准输出
     * @param $msg
     */
    public static function stdout($msg)
    {
        if (!is_scalar($msg)) {
            $msg = var_export($msg, true);
        }
        fwrite(STDOUT, $msg);
    }
}