<?php
namespace firegit\http;

class Controller
{
    var $method;
    /**
     * @var \firegit\http\Request
     */
    protected $request;
    /**
     * @var \firegit\http\Response
     */
    protected $response;

    function __construct($req, $res)
    {
        $this->request = $req;
        $this->response = $res;
    }

    function __call($name, $arguments)
    {
        if (in_array($name, array('get', 'gets', 'post', 'posts'))) {
            return call_user_func_array(array($this->request, $name), $arguments);
        } elseif (in_array($name, array('setView', 'setRaw', 'set', 'setException', 'setLayout'))) {
            return call_user_func_array(array($this->response, $name), $arguments);
        }
    }
}