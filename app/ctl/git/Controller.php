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
                'url' => 'http://' . $this->request->host . '/' . $this->gitGroup . '/' . $this->gitName . '.git',
            ),
        ));

        $this->response->setLayout('layout/common.phtml');
    }

    function index_action()
    {
        $this->tree_action();
    }

    function tree_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        if (!$this->gitPath) {
            $dir = './';
        } else {
            $dir = './' . $this->gitPath . '/';
        }
        $nodes = $reposite->listFiles($this->gitBranch, $dir);

        $branches = $reposite->listBranches();
        // 获取
        $this->response->set(array(
            'nodes' => $nodes,
            'branches' => $branches,
        ))->setView('git/index.phtml');
    }

    function blob_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $node = $reposite->getBlob($this->gitBranch, $this->gitPath);
        switch ($this->request->ext) {
            case 'md':
                require_once VENDOR_ROOT . '/parsedown/Parsedown.php';
                $parsedown = new \Parsedown();
                $node['content'] = $parsedown->text($node['content']);
                break;
            case 'php':
            case 'json':
            case 'css':
            case 'js':
            case 'xml':
            case 'html':
            case 'htm':
            case 'java':
            case 'py':
            case 'gitignore':
                $node['content'] = '<pre><code class="' .
                    $this->request->ext
                    . '">' . htmlspecialchars($node['content']) . '</code></pre>';
                break;
        }
        $this->response->set('node', $node)->setView('git/blob.phtml');
    }

    function commits_action()
    {
        $fromCommit = isset($_GET['from']) ? $_GET['from'] : $this->gitBranch;
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $commits = $reposite->listCommits($fromCommit);

        $branches = $reposite->listBranches();

        $this->response
            ->set(array(
                'navType' => 'commit',
                'commits' => $commits,
                'branches' => $branches,
            ))
            ->setView('git/commits.phtml');
    }

    function branches_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $branches = $reposite->listBranches();
        $this->response
            ->set(array(
                'navType' => 'branch',
                'branches' => $branches,
            ))
            ->setView('git/branches.phtml');
    }
}