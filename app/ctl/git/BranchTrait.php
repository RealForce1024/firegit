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

        $reposite = new \firegit\git\Reposite($this->gitGroup, $this->gitName);
        $ret = $reposite->newBranch($orig, $dest);
        if ($ret > 0) {
            throw new \Exception('branch.u_createFailed code:' . $ret);
        }

    }

    /**
     * 删除分支
     */
    function _del_branch_action()
    {
        $branch = implode('/', func_get_args());
        $reposite = new \firegit\git\Reposite($this->gitGroup, $this->gitName);
        $ret = $reposite->delBranch($branch);
        if ($ret > 0) {
            throw new \Exception('branch.u_delBranchFailed code:' . $ret);
        }
    }
}