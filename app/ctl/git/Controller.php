<?php
namespace firegit\app\ctl\git;


use \firegit\git\Reposite;

class Controller extends \firegit\http\Controller
{
    private $gitGroup;
    private $gitName;
    private $gitPath;
    private $gitBranch;

    function _before()
    {
        $this->gitGroup = $_SERVER['GIT_GROUP'];
        $this->gitName = $_SERVER['GIT_NAME'];
        $this->gitPath = $_SERVER['GIT_PATH'];
        $this->gitBranch = $_SERVER['GIT_BRANCH'];

        $this->response->set(array(
            'git' => array(
                'group' => $this->gitGroup,
                'name' => $this->gitName,
                'path' => $this->gitPath,
                'branch' => $this->gitBranch,
            ),
        ));


    }

    function index_action($args)
    {
        $this->tree_action();
    }

    function tree_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        if (!$this->gitPath) {
            $dir = './';
        } else {
            $dir = './'.$this->gitPath.'/';
        }
        $nodes = $reposite->listFiles($this->gitBranch, $dir);

        $branches = $reposite->listBranches();
        // 获取
        $this->response->set(array(
            'nodes' => $nodes,
            'branches' => $branches,
        ))->display('git/index.phtml');
    }

    function blob_action()
    {
        var_dump($this);
    }

    function commits_action()
    {
        $fromCommit = isset($_GET['from']) ? $_GET['from'] : $this->gitBranch;
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $commits = $reposite->listCommits($fromCommit);

        $branches = $reposite->listBranches();

        $this->response->set(array(
            'commits' => $commits,
            'branches' => $branches,
        ))->display('git/commits.phtml');
    }

    function branches_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $branches = $reposite->listBranches();
        $this->response->set(array(
            'branches' => $branches,
        ))->display('git/branches.phtml');
    }
}