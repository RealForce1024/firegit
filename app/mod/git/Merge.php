<?php
namespace firegit\app\mod\git;

use \firegit\db\Db;

const MERGE_STATUS_UNMERGED = 0;
const MERGE_STATUS_MERGING = 1;
const MERGE_STATUS_PASSED = 2;
const MERGE_STATUS_DENIED = 4;

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

        $db = Db::get('firegit');
        $db->table('fg_merge')
            ->unique('merge_id')
            ->saveBody(array(
                'repo_group' => $repoGroup,
                'repo_name' => $repoName,
                'user_id' => intval($userId),
                'title' => isset($info['title']) ? $info['title'] : $orig,
                'orig_branch' => $orig,
                'dest_branch' => $dest,
                '`desc`' => isset($info['desc']) ? $info['desc'] : '',
                'create_time' => time(),
                'merge_status' => MERGE_STATUS_UNMERGED,
            ))->insert();
        return $db->getLastInsertId();
    }

    /**
     * 处理合并
     * @param int $mergeId 合并的id
     * @param int $userId 合并的处理者
     * @param boolean $passed 是否通过
     * @param string $remark 评论
     */
    function handleMerge($mergeId, $userId, $passed, $remark)
    {
        Db::get('firegit')->table('fg_merge')
            ->where(array(
                'merge_Id' => intval($mergeId)
            ))->saveBody(array(
                'merge_user_id' => intval($userId),
                'passed' => $passed ? MERGE_STATUS_PASSED : MERGE_STATUS_DENIED,
                'merge_remark' => $remark ? $remark : '',
                'merge_time' => time(),
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
}