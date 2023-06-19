<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypeManager;
use Outlandish\Wordpress\Oowp\Util\AdminUtils;
use Outlandish\Wordpress\Oowp\Util\ArrayHelper;
use Outlandish\Wordpress\Oowp\Util\ReflectionUtils;
use Outlandish\Wordpress\Oowp\Util\StringUtils;
use Outlandish\Wordpress\Oowp\WpThemeHelper;

/**
 * This class contains functions which are shared across all post types, and across all sites.
 *
 * These properties of WP_Post are proxied here.
 * @property int $ID;
 * @property int $post_author
 * @property string $post_date
 * @property string $post_date_gmt
 * @property string $post_content
 * @property string $post_title
 * @property string $post_excerpt
 * @property string $post_status
 * @property string $comment_status
 * @property string $ping_status
 * @property string $post_password
 * @property string $post_name
 * @property string $to_ping
 * @property string $pinged
 * @property string $post_modified
 * @property string $post_modified_gmt
 * @property string $post_content_filtered
 * @property int $post_parent
 * @property string $guid
 * @property int $menu_order
 * @property string $post_type
 * @property string $post_mime_type
 * @property int $comment_count
 * @property string $filter
 * @property array $ancestors
 * @property string $page_template
 */
abstract class WordpressPost
{
    /** @var array Cache for post-specific data */
    protected array $_cache = [];

    /** @var array Cache for global post data */
    protected static $_staticCache = [];

    /** @var \WP_Post */
    protected \WP_Post $post;


#region Getters, Setters, Construct, Init

    /**
     * @param int|array|object $data
     */
    public function __construct(mixed $data)
    {
        // Make sure it's an object
        $this->post = self::getPostObject($data);
    }

    /**
     * Converts the data into an internal wordpress (WP_Post) post object
     * @static
     *
     * @param mixed $data
     * @return ?\WP_Post
     */
    public static function getPostObject(mixed $data) : ?\WP_Post
    {
        if ($data instanceof \WP_Post) {
            return $data;
        }
        if (is_array($data) || is_object($data)) {
            return new \WP_Post((object)$data);
        }
        if (is_numeric($data) && is_integer($data + 0) && $data > 0) {
            $post = get_post($data);
            if (!$post) {
                throw new \RuntimeException('Post not found: ' . $data);
            }
            return $post;
        }

        throw new \RuntimeException('Invalid use of getPostObject');
    }

    /**
     * Called during PostTypeManager::registerPostType
     * Post types should normally only be registered by oowp if they're not already registered in wordpress,
     * but there may be exceptional circumstances where they might be re-registered, so this function can be overridden
     * @return bool
     */
    public static function canBeRegistered() : bool
    {
        return !post_type_exists(static::postType());
    }

    /**
     * Called after all OOWP posts have been registered
     * @static
     */
    public static function onRegistrationComplete() : void
    {
    }

    /**
     * Return the underlying WP_Post
     */
    public function getWpPost() : \WP_Post
    {
        return $this->post;
    }

    /**
     * @deprecated see getWpPost
     * @return \WP_Post
     */
    public function get_post() : \WP_Post // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        return $this->getWpPost();
    }

    /**
     * Sets the underlying WP_Post as the global post
     */
    public function setAsGlobal() : void
    {
        global $post;
        $post = $this->getWpPost();
    }

    /**
     * Override this to hook into the save event. This is called with low priority so
     * all fields should be already saved
     */
    public function onSave($postData) : void
    {
        // do nothing by default
    }

    /**
     * Proxy magic properties to WP_Post
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) : mixed
    {
        return $this->post->$name;
    }

    /**
     * Proxy magic properties to WP_Post
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, mixed $value) : void
    {
        $this->post->$name = $value;
    }

    /**
     * Proxy magic properties to WP_Post
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name) : bool
    {
        return isset($this->post->$name);
    }

    /**
     * Gets the cached value for the function that called this
     *
     * @return mixed
     */
    protected function getCacheValue() : mixed
    {
        $functionName = ReflectionUtils::getCaller();
        $value = null;
        if (array_key_exists($functionName, $this->_cache)) {
            $value = $this->_cache[$functionName];
        }
        return $value;
    }

    /**
     * Sets and returns the cached value for the function that called this
     *
     * @param mixed $value
     * @return mixed
     */
    protected function setCacheValue(mixed $value) : mixed
    {
        $this->_cache[ReflectionUtils::getCaller()] = $value;
        return $value;
    }

#endregion

#region Default getters

    /**
     * @static
     *
     * @return string The post name for this class, derived from the classname
     */
    public static function postType() : string
    {
        // if not cached, derive post type from class name
        $key = 'post_type_' . static::class;
        if (!array_key_exists($key, WordpressPost::$_staticCache)) {
            WordpressPost::$_staticCache[$key] = static::generatePostType();
        }

        return WordpressPost::$_staticCache[$key];
    }

    protected static function generatePostType() : string
    {
        $postType = null;
        $className = static::class;
        // strip out the namespace, then take a substring from the first capital letter, and un-camel case it
        $className = substr($className, strrpos($className, '\\') + 1);
        if (preg_match('/([A-Z].*)/m', $className, $regs)) {
            $postType = StringUtils::fromCamelCase($regs[1]);
        }
        if (!$postType) {
            die('Invalid post type (' . $className . ')');
        }
        return $postType;
    }

    /**
     * @static
     *
     * @return string The human-friendly name of this class, derived from the post name
     */
    public static function friendlyName() : string
    {
        return ucwords(str_replace('_', ' ', static::postType()));
    }

    /**
     * @static
     *
     * @return string The human-friendly name of this class, derived from the post name
     */
    public static function friendlyNamePlural() : string
    {
        return static::friendlyName() . 's';
    }

    /**
     * Recursively navigates from root to leaf
     * @param object $p
     * @return array
     */
    public static function walkTree(object $p) : array
    {
        $currentDescendants = $p->children;
        foreach ($p->children as $child) {
            $currentDescendants = array_merge($currentDescendants, self::walkTree($child));
        }

        return $currentDescendants;
    }

    public function getDescendants() : array
    {
        $keyed = [];
        foreach (static::fetchAll() as $post) {
            $keyed[$post->ID] = $post;
            $keyed[$post->ID]->children = [];
        }
        foreach ($keyed as $post) {
            if ($post->post_parent) {
                $keyed[$post->post_parent]->children[] = $post;
            }
        }

        $p = $keyed[$this->ID];
        return static::walkTree($p);
    }

    public function getDescendantIds() : array
    {
        $ids = [];
        foreach ($this->getDescendants() as $d) {
            $ids[] = $d->ID;
        }
        return $ids;
    }

    public function allMetadata() : mixed
    {
        return get_metadata('post', $this->ID);
    }

    /**
     * Gets the metadata (custom fields) for the post
     * @param string $name
     * @param bool $single
     * @return array|string
     */
    public function metadata(string $name, bool $single = true) : mixed
    {
        $meta = get_post_meta($this->ID, $name, $single);
        if (!$single && !$meta) {
            $meta = []; // ensure return type is an array
        }
        return $meta;
    }

    /**
     * Sets the metadata with the given key for the post
     *
     * @param string $key
     * @param mixed $value
     */
    public function setMetadata(string $key, mixed $value) : int|bool
    {
        return update_post_meta($this->ID, $key, $value);
    }

    /**
     * Deletes the metadata with the given key for the post
     *
     * @param string $key
     */
    public function deleteMetadata(string $key) : bool
    {
        return delete_post_meta($this->ID, $key);
    }

    /***************************************************************************************************************************************
     *                                                                                                                                       *
     *                                                                  TEMPLATE HELPERS                                                       *
     *                                                                                                                                       *
     ***************************************************************************************************************************************/

    public function title() : string
    {
        return apply_filters('the_title', $this->post_title, $this->ID);
    }

    public function content() : string
    {
        return apply_filters('the_content', $this->post_content);
    }

    public function date(string $format = 'd M Y') : string
    {
        return date($format, $this->timestamp());
    }

    public function modifiedDate(string $format = 'd M Y') : string
    {
        return date($format, strtotime($this->post_modified));
    }

    /**
     * @return WordpressPost|null Get parent of post (or post type)
     */
    public function parent() : ?WordpressPost
    {
        $parentId = $this->post_parent;
        if (!$parentId) {
            return static::postTypeParent();
        }

        $parent = $this->getCacheValue();
        if (!$parent) {
            $parent = $this->setCacheValue(WordpressPost::fetchById($parentId));
        }
        return $parent;
    }

    /**
     * Get array of all parent posts, beginning
     * with highest parent
     *
     * @return WordpressPost[]
     */
    public function parents() : array
    {
        $parents = [];

        $post = $this;
        while ($parent = $post->parent()) {
            $parents[] = $parent;
            $post = $parent;
        }

        return array_reverse($parents);
    }

    /**
     * If the parent of a hierarchical post type is a page, for example, this needs to be set to that ID.
     * Is mutually exclusive with postTypeParentSlug (this takes priority)
     *
     * @return int The ID of the parent post for this post type.
     */
    public static function postTypeParentId() : int
    {
        return 0;
    }

    /**
     * If the parent of a hierarchical post type is a page, for example, this needs to be set to that slug
     * Is mutually exclusive with postTypeParentId (that takes priority)
     *
     * @return string The slug of the parent post for this post type.
     */
    public static function postTypeParentSlug() : string
    {
        return '';
    }

    /**
     * Gets the parent for posts of this type, based on either the ID or slug, whichever is defined for the post type
     * @return WordpressPost|null
     */
    public static function postTypeParent() : ?WordpressPost
    {
        $key = 'post_type_parent__' . static::postType();
        if (!array_key_exists($key, WordpressPost::$_staticCache)) {
            $parentId = static::postTypeParentId();
            $parentSlug = static::postTypeParentSlug();
            if ($parentId) {
                $parent = WordpressPost::fetchById($parentId);
            } elseif ($parentSlug) {
                $parent = WordpressPost::fetchBySlug($parentSlug);
            } else {
                $parent = null;
            }
            WordpressPost::$_staticCache[$key] = $parent;
        }
        return WordpressPost::$_staticCache[$key];
    }

    /**
     * Traverses up the getParent() hierarchy until finding one with no parent, which is returned
     */
    public function getRoot() : WordpressPost
    {
        $parent = $this->parent();
        if ($parent) {
            return $parent->getRoot();
        }
        return $this;
    }

    public function timestamp() : string
    {
        return strtotime($this->post_date);
    }

    public function excerpt(int $chars = 400, ?string $content = null) : string
    {
        if (empty($content)) {
            $content = $this->content();
        }
        $content = str_replace("<!--more-->", '<span id="more-1"></span>', $content);
        //try to split on more link
        $parts = preg_split('|<span id="more-\d+"></span>|i', $content);
        $content = $parts[0];
        $content = strip_tags($content);
        $excerpt = '';
        $sentences = array_filter(explode(" ", $content));
        if ($sentences) {
            foreach ($sentences as $sentence) {
                if ((strlen($excerpt) + strlen($sentence)) < $chars && $sentence) {
                    $excerpt .= $sentence . " ";
                } else {
                    break;
                }
            }
        }

        if (!$excerpt) {
            $words = array_filter(explode(" ", $content));
            if ($words) {
                foreach ($words as $word) {
                    if ((strlen($excerpt) + strlen($word)) < $chars && $word) {
                        $excerpt .= $word . " ";
                    } else {
                        break;
                    }
                }
            }
        }

        $excerpt = trim(str_replace('&nbsp;', ' ', $excerpt));
        if (preg_match('%\w|,|:%i', substr($excerpt, -1))) {
            $excerpt = $excerpt . "...";
        }

        return ($excerpt);
    }

    public function permalink(bool $leaveName = false) : string
    {
        if ($this->isHomepage()) {
            return rtrim(get_bloginfo('url'), '/') . '/';
        }
        return get_permalink($this->ID, $leaveName);
    }

    /**
     * Fetches all posts (of any post_type) whose post_parent is this post, as well as
     * the root posts of any post_types whose declared postTypeParentId is this post.
     * Add 'post_type' to query args to only return certain post types for children
     * Add 'post__not_in' to query args to exclude certain pages based on id.
     *
     * @param array $queryArgs
     * @return WordpressPost[]|OowpQuery
     */
    public function children(array $queryArgs = []) : OowpQuery
    {
        $posts = [];
        $postTypes = (array_key_exists('post_type', $queryArgs) ? $queryArgs['post_type'] : 'any');
        unset($queryArgs['post_type']);
        if (!is_array($postTypes)) {
            $postTypes = [$postTypes];
        }
        $manager = PostTypeManager::get();
        foreach ($this->childPostClassNames() as $className) {
            foreach ($postTypes as $postType) {
                if ($postType === 'any' || ($postType !== 'none' && $manager->getClassName($postType) === $className)) {
                    $posts = array_merge($posts, $className::fetchRoots($queryArgs)->posts);
                }
            }
        }
        $defaults = ['post_parent' => $this->ID];
        $queryArgs = wp_parse_args($queryArgs, $defaults);
        $children = static::fetchAll($queryArgs);
        $children->posts = array_merge($children->posts, $posts);
        $children->post_count = count($children->posts);
        return $children;
    }

    /**
     * @return array Class names of WordpressPost types having this post as their parent
     */
    public function childPostClassNames() : array
    {
        $manager = PostTypeManager::get();
        $names = [];
        foreach ($manager->getPostTypes() as $postType) {
            $class = $manager->getClassName($postType);
            if ($class::postTypeParentId() == $this->ID || $class::postTypeParentSlug() == $this->post_name) {
                $names[] = $class;
            }
        }
        return $names;
    }

    /**
     * Executes a wordpress function, setting $this as the global $post first, then resets the global post data.
     * Expects the first argument to be the function, followed by any arguments
     *
     * @return mixed
     */
    protected function callGlobalPost() : mixed
    {
        global $post;
        $prevPost = $post;

        // Get requested WordPress function and arguments
        $args = func_get_args();
        $callback = array_shift($args);

        // Set up global variables to support WP function execution
        $post = $this->getWpPost();
        setup_postdata($post);

        // Call the WordPress function
        $returnVal = call_user_func_array($callback, $args);

        // Restore original global variables
        $post = $prevPost;
        wp_reset_postdata();

        return $returnVal;
    }

    /**
     * @return string|null The author's display name.
     */
    public function wpAuthorName() : ?string
    {
        return $this->callGlobalPost('get_the_author');
    }

    /**
     * @return string the Robots meta tag, should be NOINDEX, NOFOLLOW for some post types
     */
    public function robots() : string
    {
        return "";
    }

    /**
     * Gets the url for editing this post. Returns blank if $requireLoggedIn is true and the logged-in user doesn't have the right permissions
     *
     * @param bool $requireLoggedIn
     * @return string
     */
    public function editUrl($requireLoggedIn = false) : string
    {
        $url = get_edit_post_link($this->ID, '');
        if (!$url && !$requireLoggedIn) {
            $post_type_object = get_post_type_object(static::postType());
            $url = admin_url(sprintf($post_type_object->_edit_link . '&action=edit', $this->ID));
        }
        return $url;
    }

#endregion

#region HTML Template helpers

    public function htmlLink($attrs = []) : string
    {
        $attrs['href'] = $this->permalink();
        $attrString = StringUtils::makeAttributeString($attrs);
        return "<a {$attrString}>" . $this->title() . '</a>';
    }

    /**
     * @return string|null
     */
    public function htmlAuthorLink() : ?string
    {
        return $this->callGlobalPost('get_the_author_link');
    }

    /**
     * @return bool true if this is the page currently being viewed
     */
    public function isCurrentPage() : bool
    {
        $x = WordpressPost::getQueriedObject();
        return (isset($x) && $x->ID === $this->ID);
    }

    /**
     * @return bool true if this is the direct parent of the page currently being viewed
     */
    public function isCurrentPageParent() : bool
    {
        $x = WordpressPost::getQueriedObject();
        if (empty($x)) {
            return false;
        }
        $parent = $this->parent();
        return $parent && $parent->ID === $x->ID;
    }

    /**
     * @return bool true if this is an ancestor of the page currently being viewed
     */
    public function isCurrentPageAncestor() : bool
    {
        $x = WordpressPost::getQueriedObject();
        while (isset($x) && $x) {
            if ($x->ID === $this->ID) {
                return true;
            }
            $x = $x->parent();
        }
        return false;
    }


    /**
     * Gets the ID of the image used as the 'featured image' for this post (requires 'thumbnail' to be supported by
     * the post type)
     * @return int|bool
     */
    protected function featuredImageAttachmentId() : int|bool
    {
        return get_post_thumbnail_id($this->getWpPost());
    }

    public function featuredImageUrl(string $imageSize = 'thumbnail') : ?string
    {
        $image = wp_get_attachment_image_src($this->featuredImageAttachmentId(), $imageSize);
        return $image ? $image[0] : null;
    }

    /**
     * @param string $imageSize
     * @param string[] $attrs
     * @return string HTML img element or empty string on failure.
     */
    public function featuredImage(string $imageSize = 'thumbnail', array $attrs = []) : string
    {
        return wp_get_attachment_image($this->featuredImageAttachmentId(), $imageSize, 0, $attrs);
    }

    /**
     * Gets the list of elements that comprise a breadcrumb trail, each consisting of a url and title.
     * If $includeSelf is true, the title of this post is appended (as a string, not array)
     */
    public function breadcrumbs($includeSelf = true) : array
    {
        $trail = [
            [home_url(), 'Home']
        ];

        $parents = $this->parents();
        $parents = array_filter($parents);

        if ($parents) {
            $homeId = intval(get_option('page_on_front'));
            foreach ($parents as $parent) {
                if ($parent->ID !== $homeId) {
                    $trail[] = [$parent->permalink(), $parent->title()];
                }
            };
        }

        if ($includeSelf) {
            $trail[] = $this->title();
        }

        return $trail;
    }

    /**
     * Gets all attachments linked to this post
     * @return OowpQuery
     */
    public function attachments() : OowpQuery
    {
        $queryArgs = [
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'post_parent' => $this->ID
        ];
        return new OowpQuery($queryArgs);
    }

#endregion

#region Static functions

    /**
     * @static
     * Called by register(), for registering this post type
     *
     * @return array Array of arguments used by register_post
     */
    public static function getRegistrationArgs() : array
    {
        return [
            'labels' => AdminUtils::generateLabels(static::friendlyName(), static::friendlyNamePlural()),
            'public' => true,
            'has_archive' => true,
            'rewrite' => [
                'slug' => static::postType(),
                'with_front' => false
            ],
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => [
                'title',
                'editor',
                'revisions',
            ]
        ];
    }

    /**
     * Use this in combination with getCustomAdminColumnValue to add custom columns to the wp admin interface for
     * the post. Typically you'll want to modify the existing default columns passed in as `$helper`, using
     * { @param ArrayHelper $helper Contains the default columns
     * @see ArrayHelper::insertAfter() }.
     *
     * e.g. $helper->insertAfter('title', 'name', 'Name');
     *
     * @static
     *
     */
    public static function addCustomAdminColumns(ArrayHelper $helper) : void
    {
        /* do nothing */
    }

    /**
     * Use this in combination with addCustomAdminColumns to get the column value for a post
     *
     * @param string $column The name of the column, as given in addCustomAdminColumns
     * @return string
     */
    public function getCustomAdminColumnValue(string $column) : string
    {
        return '';
    }

    /**
     * Gets the queried object (i.e. the post/page currently being viewed)
     * @static
     *
     * @return null|WordpressPost
     */
    public static function getQueriedObject() : ?WordpressPost
    {
        global $ooQueriedObject;
        if (!isset($ooQueriedObject)) {
            global $wp_the_query;
            $id = $wp_the_query->get_queried_object_id();
            $ooQueriedObject = $id ? WordpressPost::fetchById($id) : null;
        }
        return $ooQueriedObject;
    }

    /**
     * Factory method for creating a post of the appropriate WordpressPost subclass, for the given data
     * @static
     *
     * @param object|int $data
     * @return WordpressPost|null
     */
    public static function createWordpressPost(mixed $data = null) : ?WordpressPost
    {
        if ($data) {
            if ($data instanceof WordpressPost) {
                return $data;
            }
            $postData = self::getPostObject($data);
            if ($postData) {
                $className = PostTypeManager::get()->getClassName($postData->post_type);
                if (!$className) {
                    $className = MiscPost::class;
                }
                if ($postData instanceof $className) {
                    return $postData;
                }
                return new $className($postData);
            }
        }
        return null;
    }

    /**
     * Factory method for creating a post of the appropriate WordpressPost subclass, for the given post ID
     * @static
     *
     * @param int|int[] $ids
     * @return WordpressPost|OowpQuery|null
     */
    public static function fetchById(mixed $ids) : WordpressPost|OowpQuery|null
    {
        if (is_array($ids) && $ids) {
            return new OowpQuery(['post__in' => $ids]);
        }

        if ($ids) {
            return static::fetchOne(['p' => $ids]);
        }

        throw new \Exception("no IDs supplied to WordpressPost::fetchById()");
    }

    public static function fetchBySlug($slug) : ?WordpressPost
    {
        return static::fetchOne(['name' => $slug]);
    }

    /**
     * @static
     *
     * @param array $queryArgs Accepts a wp_query $queryArgs array which overwrites the defaults
     * @return OowpQuery
     */
    public static function fetchAll(array $queryArgs = []) : OowpQuery
    {
        $defaults = [
            'post_type' => static::getSelfPostTypeConstraint()
        ];

        if (static::isHierarchical()) {
            $defaults['orderby'] = 'menu_order';
            $defaults['order'] = 'asc';
        }

        $queryArgs = wp_parse_args($queryArgs, $defaults);
        $query = new OowpQuery($queryArgs);

        return $query;
    }

    /**
     * @static
     * Does the same querying as in fetchAll, but only returns the IDs of the matches posts
     *
     * @param array $queryArgs Accepts a wp_query $queryArgs array which overwrites the defaults
     * @return int[]
     */
    public static function fetchIds(array $queryArgs = []): array
    {
        $queryArgs['fields'] = 'ids';
        $query = static::fetchAll($queryArgs);
        return $query->posts;
    }

    /**
     * @static
     *
     * @return null|WordpressPost
     */
    public static function fetchHomepage(): ?WordpressPost
    {
        $key = 'homepage';
        if (!array_key_exists($key, WordpressPost::$_staticCache)) {
            $id = get_option('page_on_front');
            $post = null;
            if ($id) {
                try {
                    $post = static::fetchById($id);
                } catch (\Exception $ex) {
                    // do nothing
                }
            }
            WordpressPost::$_staticCache[$key] = $post;
        }
        return WordpressPost::$_staticCache[$key];
    }

    /**
     * @return bool True if this is the site homepage
     */
    public function isHomepage(): bool
    {
        return $this->ID == get_option('page_on_front');
    }

    /**
     * Return the first post matching the arguments
     * @static
     *
     * @param array $queryArgs
     * @return null|WordpressPost
     */
    public static function fetchOne(array $queryArgs): ?WordpressPost
    {
        $queryArgs['posts_per_page'] = 1; // Force-override this rather than only setting a default.
        $defaults = [
            'post_type' => static::getSelfPostTypeConstraint()
        ];
        $queryArgs = wp_parse_args($queryArgs, $defaults);

        $query = new OowpQuery($queryArgs);
        return $query->posts ? $query->post : null;
    }

    /**
     * @static Returns the roots of this post type (i.e those whose post_parent is self::postTypeParentId)
     *
     * @param array $queryArgs
     * @return OowpQuery
     */
    public static function fetchRoots(array $queryArgs = []): OowpQuery
    {
        // TODO Perhaps the post_parent should be set properly in the database.
        //$queryArgs['post_parent'] = static::postTypeParentId();
        $queryArgs['post_parent'] = self::postTypeParentId();
        return static::fetchAll($queryArgs);
    }


    /**
     * @static
     *
     * @param ?string $postType
     * @return bool Whether or not the post type is declared as hierarchical
     */
    public static function isHierarchical(?string $postType = null): bool
    {
        if (!$postType) {
            $postType = static::postType();
        }
        return is_post_type_hierarchical($postType);
    }

    /**
     * Gets either this class's post type, or all registered post types, depending on where this was called from
     * @return string[]|string
     */
    private static function getSelfPostTypeConstraint(): array|string
    {
        $postTypeManager = PostTypeManager::get();

        // If `get*()` methods are called on abstract post classes directly (not a registered post subclass), restrict
        // to all registered oowp post types.
        if (!$postTypeManager->postClassIsRegistered(static::class)) {
            return $postTypeManager->getPostTypes();
        }

        // Otherwise, default to constraining to the type associated with the class on which the
        // method was invoked.
        return static::postType();
    }
#endregion
}
