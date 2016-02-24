<?php
namespace firegit\http;

class Response
{
    public $outputs = array();
    private $status = 'firegit.ok';
    private $tpl;
    protected $layout;
    private $raw;
    private $ex;
    private $err;

    private static $errMap = array();


    /**
     * 设置异常
     * @param $ex
     * @return $this
     */
    function setException($ex)
    {
        $msg = $ex->getMessage();
        $arr = explode(' ', $msg);
        $this->status = $arr[0];
        $this->ex = $ex;
        return $this;
    }

    /**
     * 设置错误
     * @param $err
     * @return $this
     */
    function setError($err)
    {
        $this->status = 'firegit.fatal';
        $this->err = $err;
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
                $this->layout = VIEW_ROOT . $layout;
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
        $this->layout = VIEW_ROOT . $layout;
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
     * 设置错误码的映射页面
     * @param $msg
     * @param $url
     */
    static function setErrMap($msg, $url = null)
    {
        if (is_array($msg) && $url === null) {
            foreach($msg as $k => $v) {
                self::$errMap[$k] = $v;
            }
        } else {
            self::$errMap[$msg] = $url;
        }
    }

    /**
     * 输出内容
     */
    function output(\firegit\http\Request $req)
    {
        if ($this->ex) {
            $msg = $this->ex->getMessage();

            if (isset(self::$errMap[$msg])) {
                $url = str_replace('[u]', 'http://'.$req->host.$req->rawUri, self::$errMap[$msg]);
                header('Location: ' . $url);
                exit();
            }

            $this->outputs = array(
                'msg' => $this->ex->getMessage(),
                'file' => str_replace(SITE_ROOT, '', $this->ex->getFile()),
                'line' => $this->ex->getLine(),
            );

        } else {
            if ($this->err) {
                $this->outputs = array(
                    'msg' => $this->err['msg'],
                    'file' => $this->err['file'],
                    'line' => $this->err['line'],
                );
            }
        }
        $output = array(
            'status' => $this->status,
            'data' => $this->outputs,
        );
        if ($this->tpl) {
            $ext = 'html';
            $req->contentType = 'Content-type:text/html; charset=' . $req->charset;
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
                    $view->set('req.data', $req->data);
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
            case 'ico':
                echo $this->raw;
                break;
            default:
                if ($this->raw) {
                    echo $this->raw;
                } else {
                    print_r($output);
                }
                break;
        }
    }

    /**
     * 重定向
     * @param $url
     * @param bool $permanent
     */
    function redirect($url, $permanent = false)
    {
        if ($permanent) {
            header('HTTP/1.1 301 Moved Permanently');
        }
        header('Location: '.$url);
        exit();
    }
}