<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

abstract class PostView extends OowpView
{
    /** @var WordpressPost */
    public $post;

    public function __construct()
    {
        parent::__construct();

        global $post;
        $this->post = WordpressPost::createWordpressPost($post);
    }


}