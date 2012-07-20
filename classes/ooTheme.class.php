<?php

/**
 * Subclass this in your theme's classes directory, and put all theme functionality in it instead of in functions.php
 */
class ooTheme {

	protected $allHooks = array();

	public function __construct() {
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
}
