<?php

abstract class ooRoutemasterPost extends ooPost {

	/**
	 * Generates the permalink by concatenating this post's name to its parent's (recursively)
	 * @return string
	 */
	public function permalink()
	{
		/** @var $parent ooPost */
		$parent = $this->getParent();
		$parentUrl = $parent ? $parent->permalink() : get_bloginfo('url').'/';
		$homepage = self::fetchHomepage();
		$postName = ($homepage && $this->ID == $homepage->ID) ? '' : $this->post_name . '/';
		return $parentUrl.$postName;
	}
}
