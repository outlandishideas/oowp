<?php
/*
Plugin Name: OOWP
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 0.2
*/
//
add_action('the_posts', 'create_oo_posts', 100, 2);
function create_oo_posts($has_posts, $query)
{
	if ($has_posts) {
		foreach ($query->posts as $i => $post) {
			$query->posts[$i] = ooPost::fetch($post);
		}
	}
	return $query->posts;
}

$_registeredPostClasses = array();
$_knownOowpClasses = array();

// include all matching classes in ./classes and [current theme]/classes directories,
// and register any subclasses of ooPost using their static register() function
add_action('init', '_oowp_init');
function _oowp_init()
{
	$dirs = array(
		dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes',
//		dirname(__FILE__) . '/../../themes',
		get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'classes'
	);
	foreach ($dirs as $dir) {
		oowp_initialiseClasses($dir);
	}

	// call postRegistration on all registered classes, for e.g. creating p2p connections
	global $_registeredPostClasses;
	foreach ($_registeredPostClasses as $class) {
		$class::postRegistration();
	}

	unregister_post_type('post');
	unregister_post_type('category');
	unregister_post_type('post_tags');
	if (is_admin()) {
		add_action('admin_head', 'oowp_add_admin_styles');
		wp_enqueue_script('oowp_js', plugin_dir_url(__FILE__) . 'oowp-admin.js', array('jquery'), false, true);
		add_action('admin_menu', 'oowp_customise_admin_menu');
	} else {
		wp_enqueue_style('oowp_css', plugin_dir_url(__FILE__) . 'oowp.css');
	}
}

function oowp_customise_admin_menu() {
	remove_menu_page('link-manager.php');
}

/**
 * Attempts to style each post type menu item and posts page with its own icon, as found in the theme's 'images' directory.
 * In order to be automatically styled, icon names should have the following forms:
 * - icon-{post_type} (for posts pages)
 * - icon-menu-{post_type} (for menu items)
 * - icon-menu-active-{post_type} (for menu items when active/hovered)
 */
function oowp_add_admin_styles() {
	$imagesDir = get_theme_root() . DIRECTORY_SEPARATOR . get_template() . DIRECTORY_SEPARATOR . 'images';
	$styles = array();
	global $_registeredPostClasses;
	if (is_dir($imagesDir)) {
		$handle = opendir($imagesDir);
		while (false !== ($file = readdir($handle))) {
			$fullFile = $imagesDir . DIRECTORY_SEPARATOR . $file;
			if (!filesize($fullFile)) continue;

			$imageSize = @getimagesize($fullFile);
			if (!$imageSize || !$imageSize[0] || !$imageSize[1]) continue;

			foreach (array_keys($_registeredPostClasses) as $postType) {
				if (preg_match('/icon(-menu(-active)?)?-' . $postType . '\.\w+$/', $file, $matches)) {
					if (!array_key_exists($postType, $styles)) {
						$styles[$postType] = array();
					}
					if (count($matches) == 3) {
						$type = 'active-menu';
					} else if (count($matches) == 2) {
						$type = 'menu';
					} else {
						$type = 'page';
					}
					$styles[$postType][$type] = $file;
				}
			}
		}
	}
	if ($styles) {
		$patterns = array(
			'menu' => '#adminmenu #menu-posts-{post_type} .wp-menu-image',
			'active-menu' => '#adminmenu #menu-posts-{post_type}:hover .wp-menu-image, #adminmenu #menu-posts-{post_type}.wp-has-current-submenu .wp-menu-image',
			'page' => '.icon32-posts-{post_type}'
		);
		echo '<style type="text/css">';
		foreach ($styles as $postType=>$icons) {
			foreach ($patterns as $type=>$pattern) {
				if (isset($icons[$type])) {
					$pattern = preg_replace('/{post_type}/', $postType, $pattern);
					echo $pattern . ' {
						background: url(' . get_bloginfo('template_url') . '/images/' . $icons[$type] . ') no-repeat center center !important;
					}';
				}
			}
		}
		echo '</style>';
	}
}

/**
 * Requires all files found in the given directory, and calls init() on any valid classes
 * @param $dir
 */
function oowp_initialiseClasses($dir)
{
	if (!is_dir($dir)) {
		return;
	}

	global $_knownOowpClasses;
	$handle = opendir($dir);
	while ($file = readdir($handle)) {
		$fullFile = $dir . DIRECTORY_SEPARATOR . $file;
		if (is_dir($fullFile) && !in_array($file, array('.', '..'))) {
			oowp_initialiseClasses($fullFile);
		} else if (preg_match("/(\w+)\.class\.php/", $file, $matches)) {
			oofp('requiring ' . $file);
			require_once($fullFile);
			$className = $matches[1];
			if (class_exists($className)) {
				$_knownOowpClasses[] = $className;
				if (method_exists($className, 'init')) {
					$className::init();
				}
			}
		}
	}
}


/**
 * Gets the class name for the given identifier (eg a post type).
 * Searches through the known oowp classes for one whose name is a camel-case version of the argument (ignoring the prefix)
 * @param $data
 * @param string $default
 * @return string
 */
function ooGetClassName($data, $default = 'ooPost')
{
	global $_knownOowpClasses;
	$reversedClasses = array_reverse($_knownOowpClasses);
	// generate something to look for, eg my_post_type => MyPostType
	$classStem       = to_camel_case($data, true);
	foreach ($reversedClasses as $registeredClass) {
		// extract the stem by removing the lower case prefix, eg ooMyPostType -> MyPostType
		if (preg_match('/([A-Z].*)/m', $registeredClass, $matches)) {
			$registeredStem = $matches[1];
			if ($classStem == $registeredStem) {
				return $registeredClass;
			}
		}
	}
	return $default;
}

function oofp($data, $title = null)
{
	if (class_exists('FirePHP')) {
		FirePHP::getInstance(true)->log($data, $title);
	}
}

/**
 * Translates a camel case string into a string with underscores (e.g. firstName -&gt; first_name)
 * @param    string   $str    String in camel case format
 * @return    string            $str Translated into underscore format
 */
function from_camel_case($str)
{
	$str[0] = strtolower($str[0]);
	$func   = create_function('$c', 'return "_" . strtolower($c[1]);');
	return preg_replace_callback('/([A-Z])/', $func, $str);
}

/**
 * Translates a string with underscores into camel case (e.g. first_name -&gt; firstName)
 * @param    string   $str                     String in underscore format
 * @param    bool     $capitalise_first_char   If true, capitalise the first char in $str
 * @return   string                              $str translated into camel caps
 */
function to_camel_case($str, $capitalise_first_char = false)
{
	if ($capitalise_first_char) {
		$str[0] = strtoupper($str[0]);
	}
	$func = create_function('$c', 'return strtoupper($c[1]);');
	return preg_replace_callback('/_([a-z])/', $func, $str);
}

if (!function_exists('unregister_post_type')) :
	function unregister_post_type($post_type)
	{
		global $wp_post_types;
		if (isset($wp_post_types[$post_type])) {
			unset($wp_post_types[$post_type]);

			add_action('admin_menu', function() use ($post_type)
			{
				if ($post_type == 'post') {
					remove_menu_page('edit.php');
				} else {

					remove_menu_page('edit.php' . $post_type == 'post' ? "" : '?post_type=' . $post_type);
				}

			}, $post_type);
			return true;
		}
		return false;
	}
endif;


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
function unregister_taxonomy($taxonomy, $object_type = '')
{
	global $wp_taxonomies;

	if (!isset($wp_taxonomies[$taxonomy]))
		return false;

	if (!empty($object_type)) {
		+$i = array_search($object_type, $wp_taxonomies[$taxonomy]->object_type);

		if (false !== $i)
			unset($wp_taxonomies[$taxonomy]->object_type[$i]);

		if (empty($wp_taxonomies[$taxonomy]->object_type))
			unset($wp_taxonomies[$taxonomy]);
	} else {
		unset($wp_taxonomies[$taxonomy]);
	}

	return true;
}

/**
 * Inserts the (key, value) pair into the array, after the given key. If the given key is not found,
 * it is inserted at the end
 * @param $array
 * @param $afterKey
 * @param $key
 * @param $value
 * @return array
 */
function array_insert_after($array, $afterKey, $key, $value) {
	if (array_key_exists($afterKey, $array)) {
		$output = array();
		foreach ($array as $a=>$b) {
			$output[$a] = $b;
			if ($a == $afterKey) {
				$output[$key] = $value;
			}
		}
		return $output;
	} else {
		$array[$key] = $value;
		return $array;
	}
}

/**
 * Inserts the (key, value) pair into the array, before the given key. If the given key is not found,
 * it is inserted at the beginning
 * @param $array
 * @param $beforeKey
 * @param $key
 * @param $value
 * @return array
 */
function array_insert_before($array, $beforeKey, $key, $value) {
	$output = array();
	if (array_key_exists($beforeKey, $array)) {
		foreach ($array as $a=>$b) {
			if ($a == $beforeKey) {
				$output[$key] = $value;
			}
			$output[$a] = $b;
		}
	} else {
		$output[$key] = $value;
		foreach ($array as $a=>$b) {
			$output[$a] = $b;
		}
	}
	return $output;
}

function oowp_generate_labels($singular, $plural = null) {
	if (!$plural) {
		$plural = $singular . 's';
	}
	return array(
		'name' => $plural,
		'singular_name' => $singular,
		'add_new' => 'Add New',
		'add_new_item' => 'Add New ' . $singular,
		'edit_item' => 'Edit ' . $singular,
		'new_item' => 'New ' . $singular,
		'all_items' => 'All ' . $plural,
		'view_item' => 'View ' . $singular,
		'search_items' => 'Search ' . $plural,
		'not_found' =>  'No ' . $plural . ' found',
		'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
		'parent_item_colon' => 'Parent ' . $singular . ':',
		'menu_name' => $plural
	);
}

function oowp_print_right_now_count($count, $postName, $singular, $plural, $status = null) {
	$num = number_format_i18n($count);
	$text = _n($singular, $plural, intval($count) );

	if ( current_user_can( 'edit_posts' ) ) {
		$link = 'edit.php?post_type=' . $postName;
		if ($status) {
			$link .= '&post_status='.$status;
		}
		$num = "<a href='$link'>$num</a>";
		$text = "<a href='$link'>$text</a>";
	}

	echo '<tr>';
	echo '<td class="first b b-' . $postName . '">' . $num . '</td>';
	echo '<td class="t ' . $postName . '">' . $text . '</td>';
	echo '</tr>';
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
