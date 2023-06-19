<?php

namespace Outlandish\Wordpress\Oowp\PostTypes\Traits;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypeManager;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

/**
 * Trait to be used in wordpress post classes when making use of posts-to-posts plugin.
 * Adds functionality for registering, creating and querying connections
 */
trait Connectable
{
    /**
     * @param int|object|WordpressPost $post
     * @param array $meta
     * @param ?string $connectionName
     */
    public function connect(mixed $post, array $meta = [], ?string $connectionName = null) : void
    {
        $post = WordpressPost::createWordpressPost($post);
        if ($post) {
            if (!$connectionName) {
                $connectionName = PostTypeManager::get()->generateConnectionName(self::postType(), $post->post_type);
            }
            /** @var \P2P_Directed_Connection_Type $connectionType */
            $connectionType = p2p_type($connectionName);
            if ($connectionType) {
                $p2pId = $connectionType->connect($this->ID, $post->ID);
                foreach ($meta as $key => $value) {
                    p2p_update_meta($p2pId, $key, $value);
                }
            }
        }
    }

    /**
     * @param string|string[] $targetPostType e.g. post, event - the type of connected post(s) you want
     * @param bool $single Just return the first/only post?
     * @param array $queryArgs Augment or overwrite the default parameters for the WP_Query
     * @param bool $hierarchical If this is true the the function will return any post that is connected to this post *or any of its descendants*
     * @param string|string[] $connectionName If specified, only this connection name is used to find the connected posts (defaults to any/all connections to $targetPostType)
     * @return null|OowpQuery|WordpressPost
     */
    public function connected(
        string|array $targetPostType,
        bool $single = false,
        array $queryArgs = [],
        bool $hierarchical = false,
        string|array $connectionName = null
    ) : null|OowpQuery|WordpressPost {
        if (!function_exists('p2p_register_connection_type')) {
            // ensure return type is valid even if posts-to-posts isn't present
            return $single ? null : new OowpQuery(null);
        }

        if (!is_array($targetPostType)) {
            $targetPostType = [$targetPostType];
        }
        $manager = PostTypeManager::get();
        $postType = self::postType();

        if (!$connectionName) {
            $connectionName = $manager->getConnectionNames($postType, $targetPostType);
        } elseif (!is_array($connectionName)) {
            $connectionName = [$connectionName];
        }

        $defaults = array(
            'connected_type' => $connectionName,
            'post_type' => $targetPostType,
        );

        // ignore $hierarchical = true if this post type is not hierarchical
        if ($hierarchical && !self::isHierarchical($postType)) {
            $hierarchical = false;
        }

        if ($hierarchical) {
            $defaults['connected_items'] = array_merge($this->getDescendantIds(), array($this->ID));
        } else {
            $defaults['connected_items'] = $this->ID;
        }

        // use the menu order if $hierarchical is true, or any of the target post types are hierarchical
        $useMenuOrder = $hierarchical;
        if (!$useMenuOrder) {
            foreach ($targetPostType as $otherPostType) {
                if (self::isHierarchical($otherPostType)) {
                    $useMenuOrder = true;
                    break;
                }
            }
        }
        if ($useMenuOrder) {
            $defaults['orderby'] = 'menu_order';
            $defaults['order'] = 'asc';
        }

        $queryArgs = array_merge($defaults, $queryArgs);
        $result = new OowpQuery($queryArgs);

        if ($hierarchical) { //filter out any duplicate posts
            $post_ids = array();
            foreach ($result->posts as $i => $post) {
                if (in_array($post->ID, $post_ids)) {
                    unset($result->posts[$i]);
                }

                $post_ids[] = $post->ID;
            }
        }

        $toReturn = $single ? null : $result;
        if (!$single) {
            $toReturn = $result;
        } elseif (!empty($result->posts)) {
            $toReturn = $result->posts[0];
        }

        return $toReturn;
    }

    /**
     * @static Creates a p2p connection to another post type
     *
     * @param string $targetPostType The post_type of the post type you want to connect to
     * @param array $parameters These can overwrite the defaults. Do not specify connection_name, use $connectionName instead
     * @param string $connectionName
     * @return mixed
     */
    public static function registerConnection($targetPostType, $parameters = [], $connectionName = null)
    {
        return PostTypeManager::get()
            ->registerConnection(self::postType(), $targetPostType, $parameters, $connectionName);
    }

    /**
     * @return array Post types of WordpressPost types that are connected to this post type
     */
    public static function connectedPostTypes()
    {
        return PostTypeManager::get()->getConnectedPostTypes(self::postType());
    }

    /**
     * @return array Class names of WordpressPost types that are connected to this post type
     */
    public static function connectedClassNames()
    {
        $manager = PostTypeManager::get();
        $names = [];
        foreach (self::connectedPostTypes() as $postType) {
            $names[] = $manager->getClassName($postType);
        }
        return $names;
    }
}
