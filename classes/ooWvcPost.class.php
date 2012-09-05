<?php

abstract class ooWvcPost extends ooPost {
	public function permalink() {
		$homepage = Application::getInstance()->homepage();
		if ($homepage && $homepage->ID == $this->ID) {
			return get_bloginfo('url');
		}
		return parent::permalink();
	}


//	public function permalink()
//	{
//		$parent = $this->getParent();
//		$parentUrl = $parent ? $parent->permalink() : get_bloginfo('url').'/';
//		$homepage = Application::getInstance()->homepage();
//		$postName = ($this->ID == $homepage->ID) ? '' : $this->post_name . '/';
//		return $parentUrl.$postName;
//	}

	/**
	 * Prepends the ancestor list with the home page
	 * @return array|int
	 */
	function breadcrumbs() {
		$ancestors = parent::breadcrumbs();
		$home = Application::getInstance()->homepage();
		if ($home && $this->ID != $home->ID) {
			array_unshift($ancestors, $home->htmlLink());
		}
		return $ancestors;
	}
}
