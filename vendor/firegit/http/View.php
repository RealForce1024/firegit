<?php
namespace firegit\http;

/**
 * Class View
 * @package firegit
 */
class View
{
    var $v = array();
    private $layout;
    private $tpl;
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

    /**
     * 显示
     * @param $tpl
     * @throws \Exception
     */
    function display($tpl)
    {
        if (!$this->layout) {
            echo $this->fetch($tpl);
        } else {
            $this->tpl = $tpl;
            echo $this->fetch($this->layout);
        }
    }

    /**
     * 获取模板内容
     * @param $tpl
     * @return string
     * @throws \Exception
     */
    function fetch($tpl)
    {
        if (!is_readable($tpl)) {
            throw new \Exception('firegit.u_notfound view='.$tpl);
        }
        ob_start();
        include $tpl;
        return ob_get_clean();
    }

    /**
     * 设置模板
     * @param $layout
     */
    function setLayout($layout)
    {
        $this->layout = $layout;
    }

    /**
     * 获取内容块
     * @return string
     */
    function getContent()
    {
        return $this->fetch($this->tpl);
    }
}