<?php

/**
 * Subclass this in your theme's classes directory, and put all theme functionality in it instead of in functions.php
 */
class ooTheme {

	protected $allHooks = array();
    private static $instance;


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
    public function siteURL($slash = null, $protocol = null) {
        return site_url($slash, $protocol);
    }

	public function assetUrl($relativePath) {
		$relativePath = '/' . ltrim($relativePath, '/');
		return $this->siteInfo('template_directory') . $relativePath;
	}

	public function imageUrl($fileName) {
		return $this->assetUrl('/images/' . $fileName);
	}

	public function url() {
		return get_template_directory_uri();
	}
	/**
	 * @deprecated
	 * @return string
	 */
	public function siteThemeURL() {
        return $this->url();
    }
    public function directory() {
        return get_stylesheet_directory();
    }

    public function siteTitle() {
        return $this->siteInfo('name');
    }

	public function htmlTitle() {
		return wp_title('&laquo;', true, 'right') . ' ' . $this->siteTitle();
	}

    public function  addImageSizes($parent = true){
        if ( function_exists( 'add_image_size' ) ) {
            add_image_size( 'category-thumb', 300, 9999 ); //300 pixels wide (and unlimited height)
            add_image_size( 'homepage-thumb', 220, 180, true ); //(cropped)
        }
    }

	public function postClass($postType) {
		global $_registeredPostClasses;
		return $_registeredPostClasses[$postType];
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

}
