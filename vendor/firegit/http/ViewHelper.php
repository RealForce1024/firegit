<?php
namespace firegit\http;

class ViewHelper
{
    /**
     * @var \firegit\http\View
     */
    var $view;
    function __construct($view)
    {
        $this->view = $view;
    }
}