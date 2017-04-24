<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\WordpressTheme;

abstract class OowpView
{
    protected $theme;

    public function __construct()
    {
        $this->theme = WordpressTheme::getInstance();
    }

    public function toHtml($args = [])
    {
        ob_start();
        $this->render($args);
        $html = ob_get_contents();
        ob_end_flush();
        return $html;
    }

    public abstract function render($args = []);
}