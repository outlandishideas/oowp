<?php

abstract class ooRoutemasterPost extends ooPost {

	/**
	 * Generates the permalink by concatenating this post's name to its parent's (recursively)
	 * @param bool $leaveName
	 * @return string
	 */
	public function permalink($leaveName = false) {
		/** @var $parent ooPost */
		$parent = $this->getParent();
		$parentUrl = $parent ? $parent->permalink() : get_bloginfo('url') . '/';
		$homepage = self::fetchHomepage();
		if ($homepage && $this->ID == $homepage->ID) {
			return $parentUrl;
		} else {
			$postName = $leaveName ? '%postname%' : $this->post_name;
			return $parentUrl . $postName . '/';
		}
	}
}
