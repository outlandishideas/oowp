<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

abstract class RoutemasterPost extends WordpressPost
{
    /**
     * Generates the permalink by concatenating this post's name to its parent's (recursively)
     * @param bool $leaveName
     * @return string
     */
    public function permalink(bool $leaveName = false) : string
    {
        $parent    = $this->parent();
        $parentUrl = $parent ? $parent->permalink() : get_bloginfo('url') . '/';
        if ($this->isHomepage()) {
            return $parentUrl;
        }
        $postName = $leaveName ? '%postname%' : $this->post_name;
        return $parentUrl . $postName . '/';
    }
}
