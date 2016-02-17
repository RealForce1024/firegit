<?php
namespace firegit\app\mod\git;

use \firegit\db\Db;

const MERGE_STATUS_UNMERGED = 0x1;
const MERGE_STATUS_MERGING = 0x2;
const MERGE_STATUS_MERGED = 0x4;
const MERGE_STATUS_FAILED = 0x8;
const MERGE_STATUS_CANCELED = 0x10;

class Merge
{

    /**
     * 创建合并请求
     * @param $repoGroup 库的组名
     * @param $repoName 库的名称
     * @param $orig  来源分支
     * @param $dest  目的分支
     * @param $userId 用户ID
     * @param array $info 合并信息
     * <code>array(
     *   'title' => '标题',
     *   'desc' => '简介',
     * )</code>
     * @throws \Exception
     * @return int 合并ID
     */
    function addMerge($repoGroup, $repoName, $orig, $dest, $userId, array $info = array())
    {
        $reposite = new \firegit\git\Reposite($repoGroup, $repoName);
        $branches = $reposite->listBranches();
        if (!isset($branches[$orig])) {
            throw new \Exception('merge.origNotFound branch=' . $orig);
        }
        if (!isset($branches[$dest])) {
            throw new \Exception('merge.destNotFound branch=' . $dest);
        }

        // 获取原始分支和目标分支的hash值
        $origHash = $reposite->getBranchHash($orig);
        $destHash = $reposite->getBranchHash($dest);
        if (!$origHash || !$destHash) {
            throw new \Exception('merge.hashGetFailed');
        }

        $db = Db::get('firegit');
        $db->table('fg_merge')
            ->unique('merge_id')
            ->saveBody(array(
                'repo_group' => $repoGroup,
                'repo_name' => $repoName,
                'user_id' => intval($userId),
                'title' => isset($info['title']) ? $info['title'] : $orig,
                'orig_branch' => $orig,
                'orig_hash' => $origHash,
                'dest_branch' => $dest,
                'dest_hash' => $destHash,
                '`desc`' => isset($info['desc']) ? $info['desc'] : '',
                'create_time' => time(),
                'merge_status' => MERGE_STATUS_UNMERGED,
            ))->insert();
        return $db->getLastInsertId();
    }

    /**
     * 将指定的合并对应的合并请求标志为合并成功
     * @param $repoGroup
     * @param $repoName
     * @param $merges
     * @return array
     */
    function handleMerges($repoGroup, $repoName, $merges)
    {
        // 现获取所有的合并
        $db = Db::get('firegit');
        $rows = $db
            ->table('fg_merge')
            ->field('merge_id', 'orig_hash', 'dest_hash')
            ->where(array(
                'repo_group' => $repoGroup,
                'repo_name' => $repoName,
                'merge_status!' => MERGE_STATUS_CANCELED
            ))
            ->order('merge_id', false)
            ->get();
        foreach ($merges as $key => $merge) {
            foreach($rows as $key => $row) {
                if ($merge['orig'] == $row['orig_hash'] && $merge['dest'] == $row['dest_hash']) {
                    $this->endMerge($row['merge_id'], MERGE_STATUS_MERGED);
                    unset($rows[$key]);
                }
            }
        }
    }

    /**
     * 处理合并
     * @param int $mergeId 合并的id
     * @param int $userId 合并的处理者
     * @param boolean $passed 是否通过
     * @param string $remark 评论
     */
    function beginMerge($mergeId, $userId, $passed, $remark)
    {
        Db::get('firegit')->table('fg_merge')
            ->where(array(
                'merge_Id' => intval($mergeId)
            ))->saveBody(array(
                'merge_user_id' => intval($userId),
                'passed' => $passed ? 1 : 0,
                'merge_remark' => $remark ? $remark : '',
                'merge_status' => 2,
                'merge_time' => time(),
            ))->update();
    }

    /**
     * 更改合并状态
     * @param $mergeId
     * @param boolean $success 合并是否成功
     * @param string $log
     */
    function endMerge($mergeId, $success, $log = '')
    {
        Db::get('firegit')->table('fg_merge')
            ->where(array(
                'merge_Id' => intval($mergeId)
            ))->saveBody(array(
                'merge_status' => $success ? MERGE_STATUS_MERGED : MERGE_STATUS_FAILED,
                'merge_log' => mb_substr($log, 0, 1023)
            ))->update();
    }

    /**
     * @param $repoGroup
     * @param $repoName
     * @param $page
     * @param $num
     * @return array
     */
    function pagedGetMerges($repoGroup, $repoName, $page, $num)
    {
        $db = Db::get('firegit');
        $rows = $db
            ->table('fg_merge')
            ->where(array(
                'repo_group' => $repoGroup,
                'repo_name' => $repoName,
            ))
            ->limit($page * $num, $num)
            ->order('merge_id', false)
            ->get(true);
        return array(
            'total' => $db->getFoundRows(),
            'list' => $rows,
        );
    }

    function getMerge($mergeId)
    {
        return Db::get('firegit')
            ->table('fg_merge')
            ->where(array(
                'merge_id' => intval($mergeId),
            ))
            ->getOne();
    }
}