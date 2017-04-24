<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

/**
 * As WordpressPost is abstract, this class can be used for entities that have no real existence, e.g. 404 pages
 */
class FakePost extends WordpressPost {
    public function __construct($args = array()) {
        //set defaults
        $postArray = wp_parse_args($args, array(
            'ID' => 0,
            'post_parent' => 0,
            'post_title' => '',
            'post_name' => '',
            'post_content' => '',
            'post_type' => 'fake',
            'post_status' => 'publish',
            'post_date' => date('Y-m-d')
        ));

        //slugify title
        if ($postArray['post_title'] && !$postArray['post_name']) {
            $postArray['post_name'] = sanitize_title_with_dashes($postArray['post_title']);
        }

        parent::__construct($postArray);
    }

    public function permalink() {
        if (!empty($this->permalink)) {
            return $this->permalink;
        }
        return parent::permalink();
    }

    /**
     * @return string the Robots meta tag, should be NOINDEX, NOFOLLOW for some post types
     */
    public function robots(){
        return "NOINDEX, NOFOLLOW";
    }
}
