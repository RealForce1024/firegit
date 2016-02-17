<?php
namespace firegit\app\ctl\index;

require_once FR_ROOT . '/git/Manager.php';
use firegit\git\Manager;

class Controller extends \firegit\http\Controller
{
    function index_action()
    {
        $this->setLayout('layout/common.phtml')
            ->setView('index/index.phtml');
    }
}