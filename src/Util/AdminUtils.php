<?php

namespace Outlandish\Wordpress\Oowp\Util;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class AdminUtils
{
	/** @var WordpressPost[] */
	static $customColumnsCache = [];

	/**
   * Customises admin UI
	 */
  static function customiseAdmin()
	{
    $disabledPostTypes = apply_filters('oowp_disabled_post_types', ['post']);

    // make disabled post types private (can't remove them entirely)
    add_action('register_post_type_args', function ($args, $postType) use ($disabledPostTypes) {
      if (in_array($postType, $disabledPostTypes)) {
        $args['public'] = false;
      }
      return $args;
    }, 10, 2);

    // prevent users accessing the post-new.php and edit.php pages for disabled post types
    add_action('load-post-new.php', function() use ($disabledPostTypes) {
      $postType = get_current_screen()->post_type;
      if (in_array($postType, $disabledPostTypes)) {
        wp_die('Invalid post type');
      }
    });
    add_action('load-edit.php', function() use ($disabledPostTypes) {
      $postType = get_current_screen()->post_type;
      if (in_array($postType, $disabledPostTypes)) {
        wp_die('Invalid post type');
      }
		});

    add_action('oowp/all_post_types_registered', function ($postTypes) {
        $publicDir = plugin_dir_url(realpath(__DIR__ . '/../../public') . '/fake.txt');
		if (is_admin()) {
			add_action('admin_head', function() use ($postTypes) {
				self::addAdminStyles($postTypes);
			});
			add_action('admin_menu', function() {
				remove_menu_page('link-manager.php');
			});
			wp_enqueue_script('oowp_admin_js', $publicDir . '/oowp-admin.js', array('jquery'), false, true);
			wp_enqueue_style('oowp_admin_css', $publicDir . '/oowp-admin.css');
		} else {
			wp_enqueue_style('oowp_css', $publicDir . '/oowp.css');
		}

		foreach ($postTypes as $className => $postType) {
        /** @var string $postType */
        /** @var WordpressPost $className Actually a class name string, but we're using static methods on those classes */

			// add any custom columns
			add_filter("manage_edit-{$postType}_columns", function($defaults) use ($className) {
				if (isset($_GET['post_status']) && $_GET['post_status'] == 'trash') {
					return $defaults;
				} else {
					$helper = new ArrayHelper($defaults);
					$className::addCustomAdminColumns($helper);
					return $helper->array;
				}
			});

			// populate the custom columns for each post
			add_action("manage_{$postType}_posts_custom_column", function($column, $post_id) use ($className) {
				// cache each post, to avoid re-fetching
				if (!isset(self::$customColumnsCache[$post_id])) {
					$status = empty($_GET['post_status']) ? '' : $_GET['post_status'];
					$query = new OowpQuery(array('p'=>$post_id, 'posts_per_page'=>1, 'post_status'=>$status));
					self::$customColumnsCache[$post_id] = ($query->post_count ? $query->post : null);
				}
				if (self::$customColumnsCache[$post_id]) {
					echo self::$customColumnsCache[$post_id]->getCustomAdminColumnValue($column);
				}
			}, 10, 2);
		}

		// append the count(s) to the end of the 'at a glance' box on the dashboard
		add_action('dashboard_glance_items', function($items) use ($postTypes) {
			foreach ($postTypes as $className => $postType) {
				/** @var WordpressPost $className */
				if ($postType != 'post' && $postType != 'page') {
					$singular = $className::friendlyName();
					$plural = $className::friendlyNamePlural();

					$numPosts = wp_count_posts($postType);
					$postTypeObject = get_post_type_object($postType);

					if ($postTypeObject->show_ui) {
						$count = $numPosts->publish;
						$text = number_format_i18n($count) . ' ' . _n($singular, $plural, intval($count) );
						if ( current_user_can( 'edit_posts' )) {
							$icon = $postTypeObject->menu_icon ? '<span class="dashicons ' . $postTypeObject->menu_icon . '"></span>' : '';
							$iconSuffix = $icon ? 'icon' : 'no-icon';
							$items[] = "<div class='post-type-{$iconSuffix} {$postType}-count'>{$icon}<a href='edit.php?post_type={$postType}'>$text</a></div>";
						}
					}
				}
			}
			return $items;
		});
    });
	}

	/**
	 * Attempts to style each post type menu item and posts page with its own custom image icon, as found in the
	 * theme's 'images' directory.
	 * In order to be automatically styled, icon filenames should have the following forms:
	 * - icon-{post_type} (for posts pages, next to header)
	 * - icon-menu-{post_type} (for menu items)
	 * - icon-menu-active-{post_type} (for menu items when active/hovered)
	 * @param string[] $postTypes
	 */
	protected static function addAdminStyles($postTypes)
	{
		$imagesDir = get_template_directory() . DIRECTORY_SEPARATOR . 'images';
		$styles = array();
		if (is_dir($imagesDir)) {
			$handle = opendir($imagesDir);
			while (false !== ($file = readdir($handle))) {
				$fullFile = $imagesDir . DIRECTORY_SEPARATOR . $file;
				if (is_dir($fullFile) || !filesize($fullFile)) {
					continue;
				}

				$imageSize = @getimagesize($fullFile);
				if (!$imageSize || !$imageSize[0] || !$imageSize[1]) {
					continue;
				}

				foreach ($postTypes as $postType) {
					if (preg_match('/icon(-menu(-active)?)?-' . $postType . '\.\w+$/', $file, $matches)) {
						if (!array_key_exists($postType, $styles)) {
							$styles[$postType] = array();
						}
						if (count($matches) == 3) {
							$type = 'active-menu';
						} else if (count($matches) == 2) {
							$type = 'menu';
						} else {
							$type = 'page';
						}
						$styles[$postType][$type] = $file;
					}
				}
			}
		}
		if ($styles) {
			$patterns = array(
				'menu' => '#adminmenu #menu-posts-{post_type} .wp-menu-image',
				'active-menu' => '#adminmenu #menu-posts-{post_type}:hover .wp-menu-image, #adminmenu #menu-posts-{post_type}.wp-has-current-submenu .wp-menu-image',
				'page' => '.icon32-posts-{post_type}'
			);
			echo '<style type="text/css">';
			foreach ($styles as $postType=>$icons) {
				foreach ($patterns as $type=>$pattern) {
					if (isset($icons[$type])) {
						$pattern = preg_replace('/{post_type}/', $postType, $pattern);
						echo $pattern . ' {
					background: url(' . get_bloginfo('template_url') . '/images/' . $icons[$type] . ') no-repeat center center !important;
				}';
					}
				}
			}
			echo '</style>';
		}
	}

    static function generateLabels($singular, $plural = null) {
        if (!$plural) {
            $plural = $singular . 's';
        }
        return array(
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'Add New',
            'add_new_item' => 'Add New ' . $singular,
            'edit_item' => 'Edit ' . $singular,
            'new_item' => 'New ' . $singular,
            'all_items' => 'All ' . $plural,
            'view_item' => 'View ' . $singular,
            'search_items' => 'Search ' . $plural,
            'not_found' =>  'No ' . $plural . ' found',
            'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'menu_name' => $plural
        );
    }


}
