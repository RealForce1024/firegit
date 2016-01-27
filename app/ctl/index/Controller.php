<?php
namespace firegit\app\ctl\index;

require_once FR_ROOT . '/git/Manager.php';
use firegit\git\Manager;

class Controller extends \firegit\http\Controller
{
    function index_action()
    {
        $group = 'ronnie';
        $repos = Manager::getReposByGroup($group);
        $this->response
            ->set(array(
                'group' => $group,
                'repos' =>$repos,
            ))
            ->display('index/index.phtml');
    }
}