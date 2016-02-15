<?php
namespace firegit\app\ctl\git;

trait BranchTrait
{
    function branches_action()
    {
        $this->setBranch();

        $branches = $this->repo->listBranches();
        $this->response
            ->set(array(
                'navType' => 'branch',
                'branches' => $branches,
                'notShowNav' => true,
            ))
            ->setView('git/branches.phtml');
    }

    /**
     * 创建新分支
     */
    function _new_branch_action()
    {
        $orig = $_POST['orig'];
        $dest = $_POST['dest'];

        if (!preg_match(GIT_BRANCH_RULE, $dest)) {
            throw new \Exception('firegit.illegalBranch');
        }

        \firegit\git\Manager::doTask(
            'newBranch',
            $this->gitGroup,
            $this->gitName,
            array(
                'orig' => $orig,
                'dest' => $dest
            )
        );
    }

    /**
     * 删除分支
     */
    function _del_branch_action()
    {
        \firegit\git\Manager::doTask(
            'delBranch',
            $this->gitGroup,
            $this->gitName,
            array(
                'branch' => $this->gitBranch,
            )
        );
    }
}