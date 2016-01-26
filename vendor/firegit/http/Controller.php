<?php
namespace firegit\http;

class Controller
{
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
}