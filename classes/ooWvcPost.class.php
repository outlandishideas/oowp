<?php

abstract class ooWvcPost extends ooPost {
	public function permalink() {
		$homepage = self::fetchHomepage();
		if ($homepage && $homepage->ID == $this->ID) {
			return get_bloginfo('url');
		}
		return parent::permalink();
	}


//	public function permalink()
//	{
//		$parent = $this->getParent();
//		$parentUrl = $parent ? $parent->permalink() : get_bloginfo('url').'/';
//		$homepage = self::fetchHomepage();
//		$postName = ($this->ID == $homepage->ID) ? '' : $this->post_name . '/';
//		return $parentUrl.$postName;
//	}

	/**
	 * Prepends the ancestor list with the home page
	 * @return array|int
	 */
	function breadcrumbs() {
		$ancestors = parent::breadcrumbs();
		$home = self::fetchHomepage();
		if ($home && $this->ID != $home->ID) {
			array_unshift($ancestors, $home->htmlLink());
		}
		return $ancestors;
	}
}
