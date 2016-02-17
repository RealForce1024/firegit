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

        $commits = $this->repo->listCommits(\firegit\git\Util::normalBranch($orig), \firegit\git\Util::normalBranch($dest));

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
        $passed = $this->post('passed') == 1;
        $remark = $this->post('remark');
        if (!$mergeId) {
            throw new \Exception('firegit.u_notfound');
        }
        $api = new \firegit\app\mod\git\Merge();
        $merge = $api->getMerge($mergeId);
        if (!$merge) {
            throw new \Exception('firegit.u_notfound');
        }

        // 检查合并状态
        switch($merge['merge_status']) {
            case 2:
                throw new \Exception('firegit.u_mergeing');
            case 4:
                throw new \Exception('firegit.u_mergedSuccess');
            case 8:
                throw new \Exception('firegit.u_mergeedFailed');
        }
        $api->beginMerge($mergeId, 10000, $passed, $remark);
        if (!$passed) {
            return;
        }

        // fastcgi_finish_request();

        // 执行真正的合并请求
        $repoTmp = TMP_ROOT.'/repos/';
        $repoDir = $repoTmp.'/'.$mergeId;
        $origDir = GIT_REPO.'/'.$merge['repo_group'].'/'.$merge['repo_name'].'.git';
        if (is_dir($repoDir)) {
            system('rm '.$repoDir. ' -rf');
        }
        mkdir($repoDir, 0777, true);
        chdir($repoDir);

        // clone远程分支
        $cmd = sprintf('git clone %s -b %s ./', $origDir, $merge['dest_branch']);
        exec($cmd, $outputs, $code);
        unset($outputs);

        system('git config user.name ronnie');
        system('git config user.email dengxiaolong@jiehun.com.cn');

        $cmd = sprintf('git fetch origin %s:%s', $merge['orig_branch'], $merge['orig_branch']);
        exec($cmd, $outputs, $code);
        unset($outputs);

        $cmd = sprintf('git merge -m "Merge remote-tracking branch \'%s\' into %s" --no-ff refs/heads/%s',
            $merge['orig_branch'],
            $merge['dest_branch'],
            $merge['orig_branch']
        );
        exec($cmd, $outputs, $code);

        // 只有合并执行成功才将其提交
        if ($code == 0) {
            unset($outputs);
            $cmd = sprintf('git push origin '.$merge['dest_branch']);
            exec($cmd, $outputs, $code);
        } else {
            $merge = new \firegit\app\mod\git\Merge();
            $merge->endMerge($mergeId, false, implode("\n", $outputs));
        }
    }

    /**
     * 合并结果
     * @throws \Exception
     */
    function merge_result_action()
    {
        $mergeId = intval($this->get('merge_id'));
        if ($mergeId <= 0) {
            throw new \Exception('firegit.u_notfound');
        }
        $api = new \firegit\app\mod\git\Merge();
        $merge = $api->getMerge($mergeId);
        if (!$merge) {
            throw new \Exception('firegit.u_notfound');
        }
        $this->set('status', $merge['merge_status']);
    }
}