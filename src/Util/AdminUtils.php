<?php

namespace Outlandish\Wordpress\Oowp\Util;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class AdminUtils
{
    /** @var WordpressPost[] */
    public static array $customColumnsCache = [];

    /**
     * Customises admin UI
     */
    public static function customiseAdmin() : void
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
        add_action('load-post-new.php', function () use ($disabledPostTypes) {
            $postType = get_current_screen()->post_type;
            if (in_array($postType, $disabledPostTypes)) {
                wp_die('Invalid post type');
            }
        });
        add_action('load-edit.php', function () use ($disabledPostTypes) {
            $postType = get_current_screen()->post_type;
            if (in_array($postType, $disabledPostTypes)) {
                wp_die('Invalid post type');
            }
        });

        add_action('oowp/all_post_types_registered', function ($postTypes) {
            $publicDir = plugin_dir_url(realpath(__DIR__ . '/../../public') . '/fake.txt');
            if (is_admin()) {
                add_action('admin_menu', function () {
                    remove_menu_page('link-manager.php');
                });
                wp_enqueue_script('oowp_admin_js', $publicDir . '/oowp-admin.js', ['jquery'], false, true);
                wp_enqueue_style('oowp_admin_css', $publicDir . '/oowp-admin.css');
            } else {
                wp_enqueue_style('oowp_css', $publicDir . '/oowp.css');
            }

            foreach ($postTypes as $className => $postType) {
                /** @var string $postType */
                /** @var WordpressPost|string $className Actually a class name string, but we're using static methods on those classes */

                // add any custom columns
                add_filter("manage_edit-{$postType}_columns", function ($defaults) use ($className) {
                    if (isset($_GET['post_status']) && $_GET['post_status'] === 'trash') {
                        return $defaults;
                    }

                    $helper = new ArrayHelper($defaults);
                    $className::addCustomAdminColumns($helper);
                    return $helper->array;
                });

                // populate the custom columns for each post
                add_action("manage_{$postType}_posts_custom_column", function ($column, $post_id) {
                    // cache each post, to avoid re-fetching
                    if (!array_key_exists($post_id, self::$customColumnsCache)) {
                        $args = [
                            'p' => $post_id,
                            'posts_per_page' => 1,
                        ];
                        if (!empty($_GET['post_status'])) {
                            $args['post_status'] = $_GET['post_status'];
                        }
                        $query = new OowpQuery($args);
                        self::$customColumnsCache[$post_id] = ($query->post_count ? $query->post : null);
                    }
                    $post = self::$customColumnsCache[$post_id];
                    if ($post) {
                        echo $post->getCustomAdminColumnValue($column);
                    }
                }, 10, 2);
            }

            // append the count(s) to the end of the 'at a glance' box on the dashboard
            add_action('dashboard_glance_items', function ($items) use ($postTypes) {
                foreach ($postTypes as $className => $postType) {
                    /** @var WordpressPost|string $className */
                    if ($postType === 'post' || $postType === 'page') {
                        continue;
                    }

                    $postTypeObject = get_post_type_object($postType);

                    if ($postTypeObject->show_ui && current_user_can($postTypeObject->cap->edit_posts)) {
                        $singular = $className::friendlyName();
                        $plural = $className::friendlyNamePlural();
                        $numPosts = wp_count_posts($postType);

                        $count = $numPosts->publish;
                        $text = number_format_i18n($count) . ' ' . _n($singular, $plural, intval($count));
                        $icon = $postTypeObject->menu_icon ? '<span class="dashicons ' . $postTypeObject->menu_icon . '"></span>' : '';
                        $iconSuffix = $icon ? 'icon' : 'no-icon';
                        $items[] = "<div class='post-type-{$iconSuffix} {$postType}-count'>{$icon}<a href='edit.php?post_type={$postType}'>$text</a></div>";
                    }
                }
                return $items;
            });
        });
    }

    public static function generateLabels($singular, $plural = null) : array
    {
        if (!$plural) {
            $plural = $singular . 's';
        }
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'Add New',
            'add_new_item' => 'Add New ' . $singular,
            'edit_item' => 'Edit ' . $singular,
            'new_item' => 'New ' . $singular,
            'all_items' => 'All ' . $plural,
            'view_item' => 'View ' . $singular,
            'search_items' => 'Search ' . $plural,
            'not_found' => 'No ' . $plural . ' found',
            'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'menu_name' => $plural
        ];
    }
}
