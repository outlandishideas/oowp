<?php

abstract class ooRoutemasterPost extends ooPost {

	/**
	 * Generates the permalink by concatenating this post's name to its parent's (recursively)
	 * @param bool $leaveName
	 * @return string
	 */
	public function permalink($leaveName = false) {
		$parent = $this->parent();
		$parentUrl = $parent ? $parent->permalink() : get_bloginfo('url') . '/';
		if ($this->isHomepage()) {
			return $parentUrl;
		} else {
			$postName = $leaveName ? '%postname%' : $this->post_name;
			return $parentUrl . $postName . '/';
		}
	}
}
