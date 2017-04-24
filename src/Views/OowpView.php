<?php

namespace Outlandish\Wordpress\Oowp\Views;

abstract class OowpView
{
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