<?php
require_once('ooPost.class.php');

/**
 * This class is a placeholder for all functions which are shared across all post types, and across all sites.
 * It should be extended for each site by e.g. oiPost or irrPost, which should in turn be extended by individual post types e.g. irrEvent, irrShopItem
 */
class ooPage extends ooPost
{
	public static function fetchAll($args){
		$args = wp_parse_args($args, array('orderby' => 'menu_order', 'order' => 'asc'));
		return parent::fetchAll($args);
	}

	public function children()
	{
		$var = 2;
		return static::fetchAll(array('post_parent' => $this->ID, 'orderby' => 'menu_order', 'order' => 'desc'));
	}

}


?>
