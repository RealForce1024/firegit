<?php
namespace firegit\app\ctl\index;

use firegit\git\Manager;

class Controller extends \firegit\http\Controller
{
    function index_action()
    {
        $this->setLayout('layout/common.phtml')
            ->setView('index/index.phtml');
    }
}