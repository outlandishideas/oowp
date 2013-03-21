<?php

/**
 * Subclass this in your theme's classes directory, and put all theme functionality in it instead of in functions.php
 */
class ooTheme {

	protected $allHooks = array();
    private static $instance;
	private $acfFields = array();

	protected function __construct(){
		$this->allHooks = array(
			'filter' => array(
				'body_class',
				'excerpt_length',
				'excerpt_more',
				'get_the_excerpt',
				'the_content',
				'rewrite_rules_array'
			),
			'action' => array(
			)
		);
	}

    /**
     * @static
     * @return ooTheme Singleton instance
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function init() {
        $this->registerHooks();
    }

	/**
	 * Loops through $allHooks, adding any filters/actions that are defined in this theme (sub)class.
	 * Eg to change the string at the end of an excerpt, simply define a function called filter_excerpt_more that returns a string
	 */
	public function registerHooks() {
		$defaultArgs = array(10, 1);
		foreach ($this->allHooks as $type=>$hooks) {
			$addHookFunction = 'add_' . $type;
			foreach ($hooks as $key => $value) {
				if (is_numeric($key)) {
					$hook = $value;
					$args = $defaultArgs;
				} else {
					$hook = $key;
					$args = $value;
				}
				$hookFunction = $type . '_' . $hook;
				if (method_exists($this, $hookFunction)) {
					$addHookFunction($hook, array($this, $hookFunction), $args[0], $args[1]);
				}
			}
		}
	}

    public function siteInfo($info) {
        return get_bloginfo($info);
    }

    /*
     * No trailing slash as standard (http://www.example.com), if trailing slash is required, include as first argument, ($a = '/')
     * second argument returns protocol for the url (http, https, etc) - see http://codex.wordpress.org/Function_Reference/site_url for more info
     */
    public function siteURL($relativePath = '') {
        return site_url(null, null) . '/' . ltrim($relativePath, '/');
    }

	/**
	 * Gets the url for an asset in this theme.
	 * With no argument, this is just the root directory of this theme
	 * @param string $relativePath
	 * @return string
	 */
	public function assetUrl($relativePath = '') {
		$relativePath = '/' . ltrim($relativePath, '/');
		return get_template_directory_uri() . $relativePath;
	}

	public function imageUrl($fileName) {
		return $this->assetUrl('/images/' . $fileName);
	}

	public function jsUrl($fileName) {
		return $this->assetUrl('/js/' . $fileName);
	}

	public function cssUrl($fileName) {
		return $this->assetUrl('/css/' . $fileName);
	}

	/**
	 * @deprecated Use assetUrl() instead
	 * @return string
	 */
	public function siteThemeURL() {
        return $this->assetUrl();
    }

    public function directory($path = '') {
        return get_stylesheet_directory() . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function siteTitle() {
        return $this->siteInfo('name');
    }

	public function htmlTitle() {
		return wp_title('&laquo;', true, 'right') . ' ' . $this->siteTitle();
	}

	/**
	 * @param bool $parent
	 * @deprecated Use addImageSize instead
	 */
	public function  addImageSizes($parent = true){
        if ( function_exists( 'add_image_size' ) ) {
            add_image_size( 'category-thumb', 300, 9999 ); //300 pixels wide (and unlimited height)
            add_image_size( 'homepage-thumb', 220, 180, true ); //(cropped)
        }
    }

	/**
	 * Adds an image size to the theme, and adds the hook that ensures the thumbnails get resized when edited through the CMS
	 * @param $name
	 * @param $width
	 * @param $height
	 * @param bool $crop
	 */
	public function addImageSize($name, $width, $height, $crop = false){
		if ( function_exists( 'add_image_size' ) ) {
			add_image_size( $name, $width, $height, $crop);
			add_action('image_save_pre', array($this, 'add_image_options'));
		}
	}

	function add_image_options($data){
		global $_wp_additional_image_sizes;
		foreach($_wp_additional_image_sizes as $size => $properties){
			update_option($size."_size_w", $properties['width']);
			update_option($size."_size_h", $properties['height']);
			update_option($size."_crop", $properties['crop']);
		}
		return $data;
	}

	public function postClass($postType) {
		global $_registeredPostClasses, $wp_post_types;
		if (!isset($wp_post_types[$postType])) {
			return null; //unregistered post type
		} elseif (!isset($_registeredPostClasses[$postType])) {
			return 'ooMiscPost'; //post type with no dedicated class
		} else {
			return $_registeredPostClasses[$postType];
		}
	}

	public function postType($postClass) {
		global $_registeredPostClasses;
		foreach ($_registeredPostClasses as $type=>$class) {
			if ($class == $postClass) {
				return $type;
			}
		}
		return null;
	}

	/**
	 * @deprecated
	 */
	public function classes() {
		return $this->postTypeClasses();
	}

	public function postTypeClasses() {
		global $_registeredPostClasses;
		return array_values($_registeredPostClasses);
	}

	public function postTypes() {
		global $_registeredPostClasses;
		return array_keys($_registeredPostClasses);
	}

	public static function slugify($label) {
		return str_replace(' ', '-', strtolower($label));
	}

	public static function labelify($slug) {
		return ucwords(str_replace('-', ' ', $slug));
	}

	/**
	 * @return wpdb
	 */
	public function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Gets an ACF options value
	 * @param $optionName
	 * @return bool|mixed|string
	 */
	public function acfOption($optionName) {
		return get_field($optionName, 'option');
	}

	/**
	 * Gets the acf definitions, keyed by their hierarchical name (using hyphens).
	 * If $name is provided, a single acf definition is returned (if found)
	 * @param $acfPostName
	 * @param null $name
	 * @return array|null
	 */
	public function acf($acfPostName, $name = null) {
		if (!isset($this->acfFields[$acfPostName])) {
			$wpdb = $this->db();
			$acfData = $wpdb->get_col("SELECT pm.meta_value FROM $wpdb->posts AS p INNER JOIN $wpdb->postmeta AS pm ON p.ID = pm.post_id WHERE p.post_name = 'acf_{$acfPostName}' AND pm.meta_key like 'field_%'");
			$acfFields = array();
			$this->populateAcf($acfFields, $acfData);
			$this->acfFields[$acfPostName] = $acfFields;
		}
		if ($name) {
			return array_key_exists($name, $this->acfFields[$acfPostName]) ? $this->acfFields[$acfPostName][$name] : null;
		} else {
			return $this->acfFields[$acfPostName];
		}
	}

	/**
	 * Recursively populates the acf definitions list
	 * @param $toPopulate
	 * @param $data array The ACF definition from the database
	 * @param string $prefix The prefix to use in the name. (only applicable to hierarchical fields, i.e. repeater fields)
	 */
	private function populateAcf(&$toPopulate, $data, $prefix = '') {
		foreach ($data as $acf) {
			$acf = maybe_unserialize($acf);
			$toPopulate[$prefix . $acf['name']] = $acf;
			if (!empty($acf['sub_fields'])) {
				$this->populateAcf($toPopulate, $acf['sub_fields'], $acf['name'] . '-');
			}
		}
	}

	public static function currentUser() {
		global $current_user;
		get_currentuserinfo();
		return $current_user;
	}
}
