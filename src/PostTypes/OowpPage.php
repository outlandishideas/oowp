<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

class OowpPage extends WordpressPost
{
    public static function postType() : string
    {
        return 'page';
    }
}
