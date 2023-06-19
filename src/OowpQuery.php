<?php

namespace Outlandish\Wordpress\Oowp;

use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;
use Traversable;

/**
 * @property $posts WordpressPost[]
 * @property $post WordpressPost
 * @property $queried_object WordpressPost
 */
class OowpQuery extends \WP_Query implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @param string|array $query
     */
    public function __construct(mixed $query = '')
    {
        $defaults = [
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        $query    = wp_parse_args($query, $defaults);

        // If there is no post type, or the post type is singular and isn't valid, replace it with any *except*
        // 'attachment' which can cause crashes on ?preview=true if a file title matches a render-able post's.
        $validPostTypes    = get_post_types();
        $requestedPostType = $query['post_type'] ?? 'any';
        if (is_scalar($requestedPostType) && !array_key_exists($requestedPostType, $validPostTypes)) {
            $query['post_type'] = array_values(array_diff($validPostTypes, ['attachment']));
        }

        parent::__construct($query);

        if ($this->query_vars['error']) {
            die('Query error ' . $this->query_vars['error']);
        }
    }

    /* Interfaces */

    public function getIterator() : Traversable
    {
        return new \ArrayIterator($this->posts);
    }

    public function offsetExists(mixed $offset) : bool
    {
        return isset($this->posts[$offset]);
    }

    public function offsetGet(mixed $offset) : mixed
    {
        return $this->posts[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value) : void
    {
        $this->posts[$offset] = $value;
    }

    public function offsetUnset(mixed $offset) : void
    {
        unset($this->posts[$offset]);
    }

    public function count() : int
    {
        return count($this->posts);
    }

    /**
     * Stores $this as the global $wp_query, executes the passed-in WP function, then reverts $wp_query
     * @return mixed
     */
    protected function callGlobalQuery() : mixed
    {
        global $wp_query;
        $args      = func_get_args();
        $function  = array_shift($args);
        $oldQuery  = $wp_query;
        $wp_query  = $this;
        $returnVal = call_user_func_array($function, $args);
        $wp_query  = $oldQuery;
        return $returnVal;
    }

    /**
     * Convert WP_Post objects to WordpressPost
     * If $query['fields'] is 'ids', then this will just return the post IDs
     * If $query['fields'] is 'id->parent', then this will return an array of objects that represents the parent-child relationships
     * See https://developer.wordpress.org/reference/classes/wp_query/#return-fields-parameter
     * @return WordpressPost[]|int[]|\stdClass[]
     */
    public function get_posts() : array // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        parent::get_posts();

        $fields = $this->query['fields'] ?? 'all';
        if ($fields && $fields !== 'all') {
            return $this->posts;
        }

        foreach ($this->posts as $i => $post) {
            $this->posts[$i] = WordpressPost::createWordpressPost($post);
        }

        if (count($this->posts)) {
            $this->post              = $this->posts[0];
            $this->queried_object    = $this->post;
            $this->queried_object_id = $this->post->ID;
        }

        return $this->posts;
    }
}
