<?php

/**
 * Subclass this in your theme's classes directory, and put all theme functionality in it instead of in functions.php
 */
class ooTheme {

	protected $allHooks = array();
    public $registeredPostClasses = array();
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
        $this->registeredPostClasses = $this->registeredPostClasses();
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
    public function siteURL($a = null, $b = null) {
        return site_url($a, $b);
    }

    public function siteThemeURL() {
        return get_template_directory_uri();
    }

    public function siteTitle() {
        return $this->siteInfo('name');
    }

    public function  addImageSizes($parent = true){
        if ( function_exists( 'add_image_size' ) ) {
            add_image_size( 'category-thumb', 300, 9999 ); //300 pixels wide (and unlimited height)
            add_image_size( 'homepage-thumb', 220, 180, true ); //(cropped)
        }
    }

    private function registeredPostClasses() {
        global $_registeredPostClasses;
        return $_registeredPostClasses;
    }



}
