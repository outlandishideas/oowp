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

$_registered_ooClasses = array();
// include all matching classes in ./classes and theme/inc/classes directories,
// and register any subclasses of ooPost using their static register() function
add_action('init', '_oowp_init');
function _oowp_init()
{
	$dirs = array(
		dirname(__FILE__) . '/classes',
//		dirname(__FILE__) . '/../../themes',
		get_stylesheet_directory() . '/classes/'
	);
	foreach ($dirs as $dir) {
		if (is_dir($dir)) {
			oowp_registerClasses($dir);
		}
	}
	global $_registered_ooClasses;
	// call the init functions where they exist (must be after registering post types above
	foreach ($_registered_ooClasses as $className) {
		if (method_exists($className, 'init')) {
			$className::init();
		}
	}

	unregister_post_type('post');
	unregister_post_type('category');
	unregister_post_type('post_tags');
	if (is_admin()) {
		wp_enqueue_script('oowp_js', plugin_dir_url(__FILE__) . 'oowp-admin.js', array('jquery'), false, true);
		add_action( 'admin_menu', 'oowp_customise_admin_menu' );
	} else {
		wp_enqueue_style('oowp_css', plugin_dir_url(__FILE__) . 'oowp.css');
	}
}

function oowp_customise_admin_menu() {
	remove_menu_page('link-manager.php');
}

function oowp_registerClasses($dir)
{
	$handle = opendir($dir);
	while ($file = readdir($handle)) {
		if (is_dir($dir . '/' . $file) && !in_array($file, array('.', '..'))) {
			oowp_registerClasses($dir . '/' . $file);
		} else if (preg_match("/\w+\.class\.php/", $file, $matches)) {
			oofp('requiring ' . $file);
			require_once($dir . '/' . $file);
			$className = str_replace('.class.php', '', $file);
			global $_registered_ooClasses;
//			if (class_exists($className) && (is_subclass_of($className, 'ooPost') || is_subclass_of($className, 'ooTerm') || is_subclass_of($className, 'ooTheme') || is_subclass_of($className, 'ooTaxonomy'))) {
			if (class_exists($className)) {
				$_registered_ooClasses[] = $className;
				if (method_exists($className, 'init')) {
					$className::register();
				}
			}
		}
	}


}


/**
 * @param $data = post_type, taxonomyName or Theme name
 */
function ooGetClassName($data, $default = 'ooPost')
{
	global $_registered_ooClasses;
	$reversedClasses = array_reverse($_registered_ooClasses);
	$classStem       = to_camel_case($data, true);
	foreach ($reversedClasses as $registeredClass) {
		preg_match('/([A-Z].*)/m', $registeredClass, $matches);
		$registeredStem = $matches[1];
		if ($classStem == $registeredStem) {
			return $registeredClass;
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

function oowp_print_right_now_count($count, $postName, $friendlyName, $status = null) {
	$num = number_format_i18n($count);
	$text = _n($count, $friendlyName, intval($count) );

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

?>
