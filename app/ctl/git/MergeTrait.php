<?php
namespace firegit\app\ctl\git;

trait MergeTrait
{
    function merge_action($mergeId)
    {
        $this->setBranch();

        $api = new \firegit\app\mod\git\Merge();
        $merge = $api->getMerge($mergeId);

        if (!$merge) {
            throw new \Exception('firegit.u_notfound');
        }
        $orig = $merge['orig_branch'];
        $dest = $merge['dest_branch'];

        $commits = $this->repo->listCommits($orig, $dest);

        $this->response->set(array(
            'merge' => $merge,
            'commits' => $this->packCommits($commits),
            'navType' => 'merge',
            'orig' => $orig,
            'dest' => $dest,
        ))->setView('git/merge.phtml');
    }

    function merges_action()
    {
        $this->setBranch();

        $merge = new \firegit\app\mod\git\Merge();
        $merges = $merge->pagedGetMerges(
            $this->gitGroup,
            $this->gitName,
            $this->_pn,
            $this->_sz);

        $branches = $this->repo->listBranches();

        $this->response->set(array(
            'total' => $merges['total'],
            'merges' => $merges['list'],
            'navType' => 'merge',
            'branches' => $branches,
            'notShowNav' => true,
        ))->setView('git/merges.phtml');
    }

    function add_merge_action()
    {
        $this->setBranch();

        $orig = $_GET['orig'];
        $dest = $_GET['dest'];

        $commits = $this->repo->listCommits($orig, $dest);

        $nCommits = array();
        foreach ($commits as $commit) {
            $day = date('Y/m/d', $commit['time']);
            $nCommits[$day][] = $commit;
        }
        $this->response->set(array(
            'commits' => $nCommits,
            'navType' => 'merge',
            'orig' => $orig,
            'dest' => $dest,
        ))->setView('git/add_merge.phtml');
    }

    function _add_merge_action()
    {
        $datas = $this->posts('orig', 'dest', 'title', 'desc');

        $merge = new \firegit\app\mod\git\Merge();
        $mergeId = $merge->addMerge(
            $this->gitGroup,
            $this->gitName,
            $datas['orig'],
            $datas['dest'],
            40,
            array(
                'title' => $datas['title'],
                'desc' => $datas['desc'],
            )
        );
        $this->set(array(
            'merge_id' => $mergeId,
        ));
    }

    /**
     * 处理合并请求
     */
    function _handle_merge_action()
    {
        $mergeId = intval($this->post('merge_id'));
        if (!$mergeId) {
            throw new \Exception('firegit.u_notfound');
        }
    }
}