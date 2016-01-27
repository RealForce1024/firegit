<?php
namespace firegit\http;

class Response
{
    private $outputs = array();
    private $status = 'firegit.ok';
    private $tpl;
    protected $layout;
    private $raw;
    private $ex;


    /**
     * 设置异常
     * @param $ex
     * @return $this
     */
    function setException($ex)
    {
        $this->ex = $ex;
        $this->status = 'firegit.fatal';
        return $this;
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
            foreach ($key as $k => $v) {
                $this->outputs[$k] = $v;
            }
            return $this;
        }
        $this->outputs[$key] = $value;
        return $this;
    }

    /**
     * 设置模板
     * @param $tpl
     * @param string $layout
     * @return $this
     */
    function setView($tpl, $layout = null)
    {
        $this->tpl = VIEW_ROOT . $tpl;
        if ($layout !== null) {
            if (!$layout) {
                $this->layout = '';
            } else {
                $this->layout = VIEW_ROOT. $layout;
            }
        }
        return $this;
    }

    /**
     * 设置模板
     * @param $layout
     * @return $this
     */
    function setLayout($layout)
    {
        $this->layout = VIEW_ROOT.$layout;
        return $this;
    }

    /**
     * 设置原始输出内容
     * @param $raw
     * @return $this
     */
    function setRaw($raw)
    {
        $this->raw = $raw;
        return $this;
    }

    /**
     * 输出内容
     */
    function output(\firegit\http\Request $req)
    {
        if ($this->ex) {
            $this->outputs = array(
                'msg' => $this->ex->getMessage(),
                'trace' => $this->ex->getTraceAsString()
            );
        }
        $output = array(
            'ret' => $this->status,
            'data' => $this->outputs,
        );
        if ($this->tpl) {
            $ext = 'html';
            $req->contentType = 'Content-type:text/html; charset='.$req->charset;
        } else {
            $ext = $req->ext;
        }
        header($req->contentType);
        switch ($ext) {
            case 'html':
            case 'htm':
            case 'js':
            case 'xml':
                if ($this->tpl) {
                    $view = new \firegit\http\View();
                    $view->set($this->outputs);
                    if ($this->layout) {
                        $view->setLayout($this->layout);
                    }
                    $view->display($this->tpl);
                } elseif ($this->raw) {
                    echo $this->raw;
                }
                break;
            case 'json':
                echo json_encode($output, $req->options['json']);
                break;

            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'png':
                header('Content-type: application/png');
                echo $this->raw;
                break;
            default:
                print_r($output);
                break;
        }
    }
}