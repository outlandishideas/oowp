<?php

namespace Outlandish\Wordpress\Oowp\Shortcodes;

use Outlandish\Wordpress\Oowp\PostTypeManager;

class ListPostsShortcode
{
    const NAME = 'oowp_list_posts';

    /**
     * Shortcode that allows access to the basic fetchAll functionality through the CMS
     * Example: [oowp_list_posts type='event' posts_per_page=3]
     *
     * @param array $params
     * @param mixed $content
     */
    static function apply($params, $content) {
        $postType = $params['type']; //what kind of post are we querying
        unset($params['type']); //don't need this any more

        $manager = PostTypeManager::get();

        if(!$manager->postTypeIsRegistered($postType)){
            if(defined('WP_DEBUG') && WP_DEBUG) {
                die('OOWP shortcode error: unknown post-type ('.$postType.')');
            }

            return;
        }
        //ok - we know it's a valid post type

        $className = $manager->getClassName($postType);

        $query = $className::fetchAll($params);

        if($query){
            foreach($query as $post){
                $post->printItem();
            }
        }
    }
}
