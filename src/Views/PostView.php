<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

abstract class PostView extends OowpView
{
    /** @var WordpressPost */
    public $post;

}