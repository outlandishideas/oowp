<?php

namespace Outlandish\Wordpress\Oowp;

/**
 * Helper class for common theme-related functionality.
 *
 * Subclass this in your theme's classes directory, and put all theme-specific functionality in its init() function
 * instead of in functions.php, then call init() inside a wordpress 'init' action listener
 */
class WordpressTheme {

    private static $instance;

	protected $allHooks = array();
	private $acfFields = array();

	protected function __construct(){
	}

    /**
     * @static
     *
     * @return self Singleton instance
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function init() {
    }

    public function siteInfo($info) {
        return get_bloginfo($info);
    }

    /*
     * Gets the URL for wordpress files, e.g. wp-blog-header.php
     * For public URLs, use homeURL()
     * No trailing slash as standard (http://www.example.com), if trailing slash is required, include as first argument, ($a = '/')
     * second argument returns protocol for the url (http, https, etc) - see http://codex.wordpress.org/Function_Reference/site_url for more info
     */
    public function siteURL($relativePath = '') {
        return site_url(null, null) . '/' . ltrim($relativePath, '/');
    }

    /*
     * No trailing slash as standard (http://www.example.com), if trailing slash is required, include as first argument, ($a = '/')
     * second argument returns protocol for the url (http, https, etc) - see http://codex.wordpress.org/Function_Reference/site_url for more info
     */
    public function homeURL($relativePath = '') {
        return home_url(null, null) . '/' . ltrim($relativePath, '/');
    }

	/**
	 * Gets the url for an asset in this theme.
	 * With no argument, this is just the root directory of this theme
	 *
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
	 *
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
	 * @deprecated Use addImageSize instead
	 *
	 * @param bool $parent
	 */
	public function  addImageSizes($parent = true){
        if ( function_exists( 'add_image_size' ) ) {
            add_image_size( 'category-thumb', 300, 9999 ); //300 pixels wide (and unlimited height)
            add_image_size( 'homepage-thumb', 220, 180, true ); //(cropped)
        }
    }

	/**
	 * Adds an image size to the theme, and adds the hook that ensures the thumbnails get resized when edited through the CMS
	 *
	 * @param string $name
	 * @param string $width
	 * @param string $height
	 * @param bool $crop
	 */
	public function addImageSize($name, $width, $height, $crop = false){
		if ( function_exists( 'add_image_size' ) ) {
			add_image_size( $name, $width, $height, $crop);
			add_action('image_save_pre', array($this, 'addImageOptions'));
		}
	}

	function addImageOptions($data){
		global $_wp_additional_image_sizes;
		foreach($_wp_additional_image_sizes as $size => $properties){
			update_option($size."_size_w", $properties['width']);
			update_option($size."_size_h", $properties['height']);
			update_option($size."_crop", $properties['crop']);
		}
		return $data;
	}

	public static function slugify($label) {
		return str_replace(' ', '-', strtolower($label));
	}

	public static function labelify($slug) {
		return ucwords(str_replace('-', ' ', $slug));
	}

	/**
	 * @return \wpdb
	 */
	public function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Gets an ACF options value
	 *
	 * @param string $optionName
	 * @return mixed
	 */
	public function acfOption($optionName) {
		return get_field($optionName, 'option');
	}

	/**
	 * Gets the acf definitions, keyed by their hierarchical name (using hyphens).
	 * If $name is provided, a single acf definition is returned (if found)
	 *
	 * @param string $acfPostName
	 * @param string $name
	 * @return array|null
	 */
	public function acf($acfPostName, $name = null) {
		if (!isset($this->acfFields[$acfPostName])) {
			$wpdb = $this->db();
			// TODO Use wpdb->prepare()
			$acfData = $wpdb->get_col("SELECT pm.meta_value FROM $wpdb->posts AS p INNER JOIN $wpdb->postmeta AS pm ON p.ID = pm.post_id WHERE p.post_name = 'acf_{$acfPostName}' AND pm.meta_key like 'field_%'");
			$acfFields = array();
			$this->populateAcf($acfFields, $acfData);
			$this->acfFields[$acfPostName] = $acfFields;
		}
		if ($name) {
			return array_key_exists($name, $this->acfFields[$acfPostName]) ? $this->acfFields[$acfPostName][$name] : null;
		}

		return $this->acfFields[$acfPostName];
	}

	/**
	 * Recursively populates the acf definitions list
	 *
	 * @param array $toPopulate
	 * @param array $data The ACF definition from the database
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
