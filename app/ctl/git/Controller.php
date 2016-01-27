<?php
namespace firegit\app\ctl\git;

class Controller extends \firegit\http\Controller
{
    private $gitGroup;
    private $gitName;
    private $gitPath;
    private $gitBranch;
    private $gitDir;

    function _before()
    {
        $this->gitGroup = $_SERVER['GIT_GROUP'];
        $this->gitName = $_SERVER['GIT_NAME'];
        $this->gitPath = $_SERVER['GIT_PATH'];
        $this->gitBranch = $_SERVER['GIT_BRANCH'];
        $this->gitDir = GIT_REPO.'/'.$this->gitGroup.'/'.$this->gitName.'.git';

        if (!is_dir($this->gitDir)) {
            throw new \Exception('firegit.u_notfound');
        }

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
        chdir($this->gitDir);
var_dump($this->gitDir);
        $cmd = sprintf('git ls-tree %s "%s" -l', $this->gitBranch, './');
        exec($cmd, $outputs, $code);
        var_dump($outputs, $code);
        // 获取
        $this->response->display('git/index.phtml');
    }

    function tree_action()
    {

    }

    function blob_action()
    {
        var_dump($this);
    }
}