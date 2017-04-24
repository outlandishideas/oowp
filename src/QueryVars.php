<?php

namespace Outlandish\Wordpress\Oowp;

class QueryVars {
	private $args;

	function __construct($data) {
		if ($data instanceof \WP_Query) {
			$this->args = $data->query_vars;
		} else {
			$this->args = $data;
		}
	}

	public function hasArg($arg) {
		return isset($this->args[$arg]);
	}

	public function arg($arg) {
		return $this->args[$arg];
	}

	public function isForPostType($postType) {
		$postTypes = $this->args['post_type'];
		if (is_array($postTypes)) {
			// TODO: Is this correct?
			return in_array($postType, $postTypes);
		} else {
			return $postTypes == 'any' || $postTypes == $postType;
		}
	}
}