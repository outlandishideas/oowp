<?php

namespace Outlandish\Wordpress\Oowp;

use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class OowpQuery extends \WP_Query implements \IteratorAggregate, \ArrayAccess, \Countable
{

    /**
     * @var WordpressPost[]
     */
    var $posts;

    /**
     * @param string|array $query
     */
    function __construct($query = '')
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

    public function getIterator()
    {
        return new \ArrayIterator($this->posts);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->posts[$offset]);
    }

    public function offsetGet($offset): bool
    {
        return $this->posts[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->posts[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->posts[$offset]);
    }

    public function count(): int
    {
        return count($this->posts);
    }

    /**
     * Stores $this as the global $wp_query, executes the passed-in WP function, then reverts $wp_query
     * @return mixed
     */
    protected function callGlobalQuery()
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
     * Prints the prev/next links for this query
     * @param string $sep
     * @param string $preLabel
     * @param string $nextLabel
     */
    public function postsNavLink($sep = '', $preLabel = '', $nextLabel = '')
    {
        $this->callGlobalQuery('posts_nav_link', $sep, $preLabel, $nextLabel);
    }

    public function queryVars()
    {
        return new QueryVars($this->query_vars);
    }

    public function sortByIds($ids)
    {
        $indexes = array_flip($ids);
        usort($this->posts, function ($a, $b) use ($indexes) {
            $aIndex = $indexes[$a->ID];
            $bIndex = $indexes[$b->ID];
            return $aIndex < $bIndex ? -1 : 1;
        });
    }

    /**
     * Convert WP_Post objects to WordpressPost
     * If $query['fields'] is 'ids', then this will just return the post IDs
     * If $query['fields'] is 'id->parent', then this will return an array of objects that represents the parent-child relationships
     * See https://developer.wordpress.org/reference/classes/wp_query/#return-fields-parameter
     * @return WordpressPost[]|int[]|\stdClass[]
     */
    public function &get_posts()
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
