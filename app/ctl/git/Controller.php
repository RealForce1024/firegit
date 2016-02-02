<?php
namespace firegit\app\ctl\git;


use \firegit\git\Reposite;

class Controller extends \firegit\http\Controller
{
    private $gitGroup;
    private $gitName;
    /**
     * @var int 每页的大小
     */
    private $_sz;
    /**
     * @var int 第几页，从0开始
     */
    private $_pn;

    /**
     * @var \firegit\git\Reposite
     */
    private $repo;

    function _before()
    {
        $this->gitGroup = $_SERVER['GIT_GROUP'];
        $this->gitName = $_SERVER['GIT_NAME'];

        $this->repo = new Reposite($this->gitGroup, $this->gitName);

        $this->response->set(array(
            'git' => array(
                'group' => $this->gitGroup,
                'name' => $this->gitName,
                'url' => 'http://' . $this->request->host . '/' . $this->gitGroup . '/' . $this->gitName . '.git',
            ),
            'prefix' => '/'.$this->gitGroup.'/'.$this->gitName.'/',
        ));

        $this->response->set('isAjax', $this->request->isAjax);
        if (!$this->request->isAjax) {
            $this->response->setLayout('layout/common.phtml');
        }

        $this->_sz = isset($_GET['_sz']) ? intval($_GET['_sz']) : 20;
        if ($this->_sz > 100) {
            $this->_sz = 100;
        }
        $this->_pn = isset($_GET['_pn']) ? intval($_GET['_pn']) : 0;
        if ($this->_pn < 0 || $this->_pn > 100) {
            $this->_pn = 0;
        }
    }

    function index_action()
    {
        $this->tree_action('master', '');
    }

    private function handleBranchAndPath($args)
    {
        $branch = array_shift($args);
        $path = implode('/', $args);

        $this->setBranch($branch);
        $this->response->outputs['git']['path'] = $path;

        return array($branch, $path);
    }

    private function setBranch($branch = 'master')
    {
        $this->response->outputs['git']['branch'] = $branch;
    }

    function tree_action()
    {
        list($branch, $path) = $this->handleBranchAndPath(func_get_args());

        $nodes = $this->repo->listFiles($branch, './'.$path.'/');

        $branches = $this->repo->listBranches();

        if (!empty($nodes['files']['readme.md'])) {
            $node = $nodes['files']['readme.md'];
            $node = $this->repo->getBlob($branch, $node['path']);
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
        list($branch, $path) = $this->handleBranchAndPath(func_get_args());

        $node = $this->repo->getBlob($branch, $path);
        $ext = $this->request->ext;
        switch ($ext) {
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
            case 'phtml':
            case 'c':
            case 'less':
            case 'sass':
            case 'coffee':
            case 'prefs':
            case 'h':
            case 'm':
            case 'mm':
            case 'xib':
            case 'gradle':
            case 'pro':
            case 'plist':
            case 'properties':
            case 'go':
                $node['content'] = '<pre class="brush: ' . $ext . '">' . htmlentities($node['content']) . '</pre>';
                break;
            case 'ico':
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'png':
                $node['content'] = '<img src="data:image/png;base64,' . base64_encode($node['content']) . '"/>';
                break;
            default:
                $node['content'] = '<div class="thumbnail" style="width:600px;height:600px;text-align:center;line-height:600px;font-size:80px;background:#EEE;">' . $ext . '文件</div>';
                break;
        }
        $this->response->set('node', $node)->setView('git/blob.phtml');
    }

    function raw_action()
    {
        list($branch, $path) = $this->handleBranchAndPath(func_get_args());
        $node = $this->repo->getBlob($branch, $path);
        $this->response->setRaw($node['content']);
    }

    function commits_action($branch)
    {
        $this->response->outputs['git']['branch'] = $branch;

        $commits = $this->repo->pagedGetCommits($branch, 20);
        $branches = $this->repo->listBranches();

        $this->response
            ->set(array(
                'navType' => 'commit',
                'commits' => $this->packCommits($commits['commits']),
                'branches' => $branches,
                'nextHash' => $commits['next'],
                'branchType' => 'commits',
            ))
            ->setView('git/commits.phtml');
    }

    function commit_action($branch)
    {
        $this->setBranch($branch);

        $diffs = $this->repo->listDiffs($branch);
        $stats = $this->repo->statCommit($branch);
        $this->response->set(array(
            'diffs' => $diffs,
            'navType' => 'commit',
            'commit' => $stats,
        ))->setView('git/commit.phtml');
    }

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

    function diff_action()
    {
        $orig = $this->get('orig');
        $dest = $this->get('dest');
        $diffs = $this->repo->listDiffs($orig, $dest);
        $this->response->set(array(
            'diffs' => $diffs,
        ))->setView('git/components/diffs.phtml');
    }

    function dcommit_action()
    {
        $orig = $this->get('orig');
        $dest = $this->get('dest');
        $commits = $this->repo->listCommits($orig, $dest);
        $this->response->set(array(
            'commits' => $this->packCommits($commits),
        ))->setView('git/components/commits.phtml');
    }

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

    private function packCommits($commits)
    {
        $nCommits = array();
        foreach ($commits as $commit) {
            $day = date('Y/m/d', $commit['time']);
            $nCommits[$day][] = $commit;
        }
        return $nCommits;
    }

    /**
     * 文件追责
     */
    function blame_action()
    {
        list($branch, $path) = $this->handleBranchAndPath(func_get_args());
        $blame = $this->repo->getBlame($path);
        $this->response
            ->set(array(
                'blame' => $blame,
                'model' => 'blame',
            ))
            ->setView('git/blame.phtml');
    }


    /**
     * 代码历史
     */
    function history_action()
    {
        list($branch, $path) = $this->handleBranchAndPath(func_get_args());
        $history = $this->repo->getHistory($path);
        $this->response
            ->set(array(
                'show_menu' => 'show',
                'model' => 'history',
                'commits' => $this->packCommits($history['commits']),
            ))
            ->setView('git/commits.phtml');
    }

}
