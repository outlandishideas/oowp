<?php
/*
Plugin Name: Object-oriented WordPress (OOWP)
Plugin URI: https://github.com/outlandishideas/oowp
Description: OOWP is a tool for WordPress theme developers that makes templating in WordPress more sensible. It replaces [The Loop](https://codex.wordpress.org/The_Loop) and contextless functions such as the_title() with object-oriented methods such as $event->title(), $event->parent() and $event->getConnected('people').
Version: 0.9
*/

use Outlandish\Wordpress\Oowp\Shortcodes\ListPostsShortcode;
use Outlandish\Wordpress\Oowp\Util\AdminUtils;

add_action('oowp/all_post_types_registered', function($postTypes) {
	AdminUtils::customiseAdmin($postTypes);
});

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
 * @package WordPress
 * @subpackage Taxonomy
 * @since 3.0
 * @uses $wp_taxonomies Modifies taxonomy object
 *
 * @param string $taxonomy Name of taxonomy object
 * @param array|string $object_type Name of the object type
 * @return bool True if successful, false if not
 */

if(!function_exists('unregister_taxonomy')){
	function unregister_taxonomy($taxonomy, $object_type = ''){
		global $wp_taxonomies;

		if (!isset($wp_taxonomies[$taxonomy]))
			return false;

		if (!empty($object_type)) {
			$i = array_search($object_type, $wp_taxonomies[$taxonomy]->object_type);

			if (false !== $i)
				unset($wp_taxonomies[$taxonomy]->object_type[$i]);

			if (empty($wp_taxonomies[$taxonomy]->object_type))
				unset($wp_taxonomies[$taxonomy]);
		} else {
			unset($wp_taxonomies[$taxonomy]);
		}

		return true;
	}
}


/**
 * @return string The full path of the wrapped template
 */
function oowp_layout_template_file() {
	return OOWP_Layout::$innerTemplate;
}

/**
 * @return string The name of the wrapped template
 */
function oowp_layout_template_name() {
	return OOWP_Layout::$templateName;
}

/**
 * This wraps all requested templates in a layout.
 *
 * Create layout.php in your root theme directory to use the same layout on all pages.
 * Create layout-{template}.php for specific versions
 *
 * Example layout: include header, sidebar and footer on all pages, and wrap standard template in a section and a div
 *
 * <?php get_header( oowp_layout_template_name() ); ?>
 *
 *   <section id="primary">
 *     <div id="content" role="main">
 *       <?php include oowp_layout_template_file(); ?>
 *     </div><!-- #content -->
 *   </section><!-- #primary -->
 *
 * <?php get_sidebar( oowp_layout_template_name() ); ?>
 * <?php get_footer( oowp_layout_template_name() ); ?>
 *
 * See http://scribu.net/wordpress/theme-wrappers.html
 */

add_filter( 'template_include', array( 'OOWP_Layout', 'wrap' ), 99 );
class OOWP_Layout {

	/**
	 * Stores the full path to the main template file
	 */
	static $innerTemplate = null;

	/**
	 * Stores the base name of the template file; e.g. 'page' for 'page.php' etc.
	 */
	static $templateName = null;

	static function wrap( $template ) {
		self::$innerTemplate = $template;

		self::$templateName = substr( basename( self::$innerTemplate ), 0, -4 );

		$templates = array( 'layout.php' );

		if ( 'index' == self::$templateName ) {
			self::$templateName = null;
		} else {
			// prepend the more specific wrapper filename
			array_unshift( $templates, sprintf( 'layout-%s.php', self::$templateName ) );
		}

		// revert to the template passed in if no layout template is found
		return locate_template( $templates ) ?: $template;
	}
}


add_shortcode(ListPostsShortcode::NAME, array(ListPostsShortcode::class, 'apply'));