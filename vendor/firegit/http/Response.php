<?php
namespace firegit\http;

class Response
{
    var $v = array();
    /**
     * 展示模板
     * @param $tpl
     * @throws \Exception
     */
    function display($tpl)
    {
        $path = VIEW_ROOT.'/'.$tpl;
        if (!is_readable($path)) {
            throw new \Exception('firegit.u_notfound view='.$path);
        }
        include $path;
    }

    /**
     * 设置变量
     * @param $key
     * @param $value
     */
    function set($key, $value)
    {
        $this->v[$key] = $value;
    }
}