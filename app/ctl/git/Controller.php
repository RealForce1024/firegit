<?php
namespace firegit\app\ctl\git;


use \firegit\git\Reposite;

class Controller extends \firegit\http\Controller
{
    private $gitGroup;
    private $gitName;
    private $gitPath;
    private $gitBranch;

    /**
     * @var int 每页的大小
     */
    private $_sz;
    /**
     * @var int 第几页，从0开始
     */
    private $_pn;

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
            'prefix' => '/' . $this->gitGroup . '/' . $this->gitName . '/',
        ));

        $this->response->set('isAjax', $this->request->isAjax);
        if (!$this->request->isAjax) {
            $this->response->setLayout('layout/common.phtml');
        }

        $this->_sz = min(isset($_GET['_sz']) ? intval($_GET['_sz']) : 0, 100);
        $this->_pn = min(isset($_GET['_pn']) ? intval($_GET['_pn']) : 20, 50);
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

        if (!empty($nodes['files']['readme.md'])) {
            $node = $nodes['files']['readme.md'];
            $node = $reposite->getBlob($this->gitBranch, $node['path']);
            require_once VENDOR_ROOT . '/parsedown/Parsedown.php';
            $parsedown = new \Parsedown();
            $node['content'] = $parsedown->text($node['content']);
            $this->response->set('readme', $node);
        }
        // 获取
        $this->response->set(array(
            'nodes' => $nodes,
            'branches' => $branches,
            'branchType' => 'tree',
        ))->setView('git/tree.phtml');
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
            case 'gitmodules':
            case 'project':
            case 'txt':
            case 'as':
            case 'classpath':
            case 'buildpath':
            case 'sh':
            case 'py':
            case 'phtml':
            case 'c':
            case 'less':
            case 'sass':
            case 'coffee':
            case 'prefs':
            case 'h':
            case 'm':
            case 'c':
            case 'mm':
            case 'xib':
            case 'gradle':
            case 'pro':
            case 'plist':
            case 'properties':
                $node['content'] = '<pre class="brush: '.$this->request->ext.'">' . htmlentities($node['content']) . '</pre>';
                break;
            case 'ico':
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'png':
                $node['content'] = '<img src="data:image/png;base64,' . base64_encode($node['content']) . '"/>';
                break;
            default:
                $node['content'] = '<div class="thumbnail" style="width:600px;height:600px;text-align:center;line-height:600px;font-size:80px;background:#EEE;">' . $this->request->ext . '文件</div>';
                break;
        }
        $this->response->set('node', $node)->setView('git/blob.phtml');
    }

    function raw_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $node = $reposite->getBlob($this->gitBranch, $this->gitPath);
        $this->response->setRaw($node['content']);
    }

    function commits_action()
    {
        $fromCommit = isset($_GET['from']) ? $_GET['from'] : $this->gitBranch;
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $commits = $reposite->listCommits($fromCommit, 20);

        $nCommits = array();
        foreach($commits['commits'] as $commit) {
            $day = date('Y/m/d', $commit['time']);
            $nCommits[$day][] = $commit;
        }

        $branches = $reposite->listBranches();

        $this->response
            ->set(array(
                'navType' => 'commit',
                'commits' => $nCommits,
                'branches' => $branches,
                'nextHash' => $commits['next'],
                'branchType' => 'commits',
            ))
            ->setView('git/commits.phtml');
    }

    function commit_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $diffs = $reposite->listDiffs($this->gitBranch);
        $stats = $reposite->statCommit($this->gitBranch);
        $this->response->set(array(
            'diffs' => $diffs,
            'navType' => 'commit',
            'commit' => $stats,
        ))->setView('git/commit.phtml');
    }

    function branches_action()
    {
        $reposite = new Reposite($this->gitGroup, $this->gitName);
        $branches = $reposite->listBranches();
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
