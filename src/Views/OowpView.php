<?php

namespace Outlandish\Wordpress\Oowp\Views;

abstract class OowpView
{
    public function __construct($args = [])
    {
        foreach ($args as $name => $value) {
            $this->$name = $value;
        }
    }

    public function toHtml($args = [])
    {
        ob_start();
        $this->render($args);
        $html = ob_get_clean();
        return $html;
    }

    abstract public function render($args = []);
}
