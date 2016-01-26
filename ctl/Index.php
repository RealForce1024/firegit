<?php
namespace firegit\ctl;

class Index extends \firegit\http\Controller
{
    function index_action()
    {
        $this->response->display('index/index.phtml');
    }
}