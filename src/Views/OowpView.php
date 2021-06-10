<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\WordpressTheme;

abstract class OowpView
{
    /** @var WordpressTheme */
    public $theme;

    public function __construct($args = [])
    {
        $this->theme = WordpressTheme::getInstance();
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

    public abstract function render($args = []);
}
