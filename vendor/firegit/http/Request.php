<?php
namespace firegit\http;


class Request
{
    var $uri;
    var $url;
    var $method;
    var $host;
    var $charset = 'utf-8';
    var $ext;
    var $contentType;
    var $options = array();
    var $rawUri;
    var $isAjax = false;
    var $data = array();

    function __construct()
    {
        $uri = $this->uri = $this->rawUri = $_SERVER['REQUEST_URI'];
        $info = parse_url($uri);

        $this->url = $info['path'];
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->host = $_SERVER['HTTP_HOST'];

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            $this->isAjax = true;
        }

        $this->ext = $ext = strtolower(pathinfo($this->rawUri, PATHINFO_EXTENSION));
        if ($this->method == 'POST' && !$ext) {
            $this->ext = $ext = 'json';
        }
        switch ($ext) {
            case 'json':
                $this->contentType = 'Content-type: application/json; charset=' . $this->charset;
                $option = JSON_UNESCAPED_UNICODE;
                if (!empty($_GET['_pretty'])) {
                    $option |= JSON_PRETTY_PRINT;
                }
                $this->options['json'] = $option;
                break;
            case 'js':
                $this->contentType = 'Content-type: application/javascript; charset=' . $this->charset;
                break;
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'png':
                $this->contentType = 'Content-type: image/' . $ext;
                break;
            case 'xml':
            case 'css':
                header('Content-type: text/' . $ext . '; charset=' . $this->charset);
                break;
            case 'html':
            case 'htm':
                $this->contentType = 'Content-type: text/html; charset=' . $this->charset;
                break;
            default:
                $this->contentType = 'Content-type: text/plain; charset=' . $this->charset;
                break;
        }
    }

    /**
     * 获取get参数
     * @param $key
     * @param null $def
     * @return null
     */
    function get($key, $def = null)
    {
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        return $def;
    }

    /**
     * 一次性获取多个get参数
     */
    function gets()
    {
        $args = func_get_args();
        $ret = array();
        foreach($args as $key) {
            if (isset($_GET[$key])) {
                $ret[$key] = $_GET[$key];
            } else {
                $ret[$key] = null;
            }
        }
        return $ret;
    }

    /**
     * 获取post参数
     * @param $key
     * @param null $def
     * @return null
     */
    function post($key, $def = null)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        return $def;
    }

    /**
     * 一次性获取多个post参数
     * @return array
     */
    function posts()
    {
        $args = func_get_args();
        $ret = array();
        foreach($args as $key) {
            if (isset($_POST[$key])) {
                $ret[$key] = $_POST[$key];
            } else {
                $ret[$key] = null;
            }
        }
        return $ret;
    }

    /**
     * 设置数据
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    function setData($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * 获取设置的数据
     * @param string $key
     * @param mixed $def
     * @return null
     */
    function getData($key, $def = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return $def;
    }
}