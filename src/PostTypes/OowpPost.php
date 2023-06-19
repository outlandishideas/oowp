<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

class OowpPost extends WordpressPost
{
    public static function postType() : string
    {
        return 'post';
    }
}
