<?php
/*
Plugin Name: Object-oriented WordPress (OOWP)
Plugin URI: https://github.com/outlandishideas/oowp
Description: OOWP is a tool for WordPress theme developers that makes templating in WordPress more sensible. It replaces [The Loop](https://codex.wordpress.org/The_Loop) and contextless functions such as the_title() with object-oriented methods such as $event->title(), $event->parent() and $event->getConnected('people').
Version: 0.9
*/

use Outlandish\Wordpress\Oowp\Shortcodes\ListPostsShortcode;
use Outlandish\Wordpress\Oowp\Util\AdminUtils;

AdminUtils::customiseAdmin();

if (!function_exists('unregister_post_type')) {
    function unregister_post_type($post_type)
    {
        global $wp_post_types;
        if (isset($wp_post_types[$post_type])) {
            unset($wp_post_types[$post_type]);

            add_action('admin_menu', function () use ($post_type) {
                remove_menu_page('edit.php' . ($post_type == 'post' ? "" : "?post_type=$post_type"));
            }, $post_type);
            return true;
        }
        return false;
    }
}


/**
 * Reverse the effects of register_taxonomy()
 *
 * @param string $taxonomy Name of taxonomy object
 * @param array|string $object_type Name of the object type
 * @return bool True if successful, false if not
 * @uses $wp_taxonomies Modifies taxonomy object
 *
 * @package WordPress
 * @subpackage Taxonomy
 * @since 3.0
 */

if (!function_exists('unregister_taxonomy')) {
    function unregister_taxonomy($taxonomy, $object_type = '')
    {
        global $wp_taxonomies;

        if (!isset($wp_taxonomies[$taxonomy])) {
            return false;
        }

        if (!empty($object_type)) {
            $i = array_search($object_type, $wp_taxonomies[$taxonomy]->object_type);

            if (false !== $i) {
                unset($wp_taxonomies[$taxonomy]->object_type[$i]);
            }

            if (empty($wp_taxonomies[$taxonomy]->object_type)) {
                unset($wp_taxonomies[$taxonomy]);
            }
        } else {
            unset($wp_taxonomies[$taxonomy]);
        }

        return true;
    }
}

add_shortcode(ListPostsShortcode::NAME, function ($params, $content) {
    ListPostsShortcode::apply($params, $content);
});
