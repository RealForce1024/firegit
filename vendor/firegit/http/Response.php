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
        ob_start();
        include $path;
        $content = ob_get_clean();
        echo $content;
    }

    /**
     * 设置变量
     * @param string|array $key
     * @param null $value
     * @return $this
     */
    function set($key, $value = null)
    {
        if (is_array($key) && $value === null) {
            foreach($key as $k => $v) {
                $this->v[$k] = $v;
            }
            return $this;
        }
        $this->v[$key] = $value;
        return $this;
    }
}