<?php

namespace Outlandish\Wordpress\Oowp\PostTypes;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypeManager;
use Outlandish\Wordpress\Oowp\Util\AdminUtils;
use Outlandish\Wordpress\Oowp\Util\ArrayHelper;
use Outlandish\Wordpress\Oowp\Util\ReflectionUtils;
use Outlandish\Wordpress\Oowp\Util\StringUtils;
use Outlandish\Wordpress\Oowp\WordpressTheme;

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

	protected $_cache = array();
	protected static $_staticCache = array();

	/**
	 * @var \WP_Post
	 */
	protected $post;


#region Getters, Setters, Construct, Init

	/**
	 * @param $data int | array | object
	 */
	public function __construct($data)
	{
		//Make sure it's an object
		$this->post = self::getPostObject($data);
	}

	/**
	 * Converts the data into an internal wordpress (WP_Post) post object
	 * @static
	 * @param mixed $data
	 * @return \WP_Post|WordpressPost
	 */
	public static function getPostObject($data)
	{
		if (is_array($data)) {
			return new \WP_Post((object)$data);
		} else if (is_object($data) && $data instanceof \WP_Post) {
			return $data;
		} else if (is_object($data)) {
			return new \WP_Post($data);
		} else if (is_numeric($data) && is_integer($data+0)) {
			return get_post($data);
		} else {
			//TODO: should this throw an exception instead?
			//this is the only way this can return a WordpressPost object
			global $post;
			return $post;
		}
	}

    /**
     * Called during PostTypeManager::registerPostType
     * Post types should normally only be registered by oowp if they're not already registered in wordpress,
     * but there may be exceptional circumstances where they might be re-registered, so this function can be overridden
     * @return bool
     */
	public static function canBeRegistered()
    {
        return !post_type_exists(self::postType());
    }

	/**
	 * Called after all OOWP posts have been registered
	 * @static
	 */
	public static function onRegistrationComplete()
	{
	}

	/**
	 * Return the underlying WP_Post
	 */
	public function get_post()
    {
	    return $this->post;
    }

    /**
     * Sets the underlying WP_Post as the global post
     */
    public function setAsGlobal()
    {
        global $post;
        $post = $this->get_post();
    }

	/**
	 * Override this to hook into the save event. This is called with low priority so
	 * all fields should be already saved
	 */
	public function onSave($postData) {
		// do nothing
	}

	/**
	 * Proxy magic properties to WP_Post
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->post->$name;
	}

	/**
	 * Proxy magic properties to WP_Post
	 * @param string $name
	 * @param mixed $value
	 * @return mixed
	 */
	public function __set($name, $value) {
		return $this->post->$name = $value;
	}

	/**
	 * Proxy magic properties to WP_Post
	 * @param string $name
	 * @return mixed
	 */
	public function __isset($name) {
		return isset($this->post->$name);
	}

	/**
	 * Gets the cached value for the function that called this
	 * @return mixed
	 */
	protected function getCacheValue() {
		$functionName = ReflectionUtils::getCaller();
		return (array_key_exists($functionName, $this->_cache) ? $this->_cache[$functionName] : null);
	}

	/**
	 * Sets and returns the cached value for the function that called this
	 * @param $value
	 * @return mixed
	 */
	protected function setCacheValue($value) {
		$this->_cache[ReflectionUtils::getCaller()] = $value;
		return $value;
	}

#endregion

#region Default getters

	protected static $postTypes = array();

	/**
	 * @static
	 * @return string the post name for this class, derived from the classname
	 */
	public static function postType()
	{
		$class = get_called_class();
		$key = $class;
		if (!array_key_exists($key, static::$postTypes)) {
			$postType = null;
			// strip out the namespace, then take a substring from the first capital letter, and un-camel case it
			$class = substr($class, strrpos($class, '\\')+1);
			if (preg_match('/([A-Z].*)/m', $class, $regs)) {
				$match = $regs[1];
				$postType = lcfirst(StringUtils::fromCamelCase($match));
			}
			static::$postTypes[$key] = $postType;
		}

		$postType = static::$postTypes[$key];
		if (!$postType) {
			die('Invalid post type (' . $class . ')');
		}
		return $postType;
	}

	/**
	 * @static
	 * @return string - the human-friendly name of this class, derived from the post name
	 */
	public static function friendlyName() {
		return ucwords(str_replace('_', ' ', static::postType()));
	}

	/**
	 * @static
	 * @return string - the human-friendly name of this class, derived from the post name
	 */
	public static function friendlyNamePlural() {
		return static::friendlyName() . 's';
	}

	/**
	 * @param $posts array Array of posts/post ids
	 */
	public function connectAll($posts) {
		foreach ($posts as $post) {
			$this->connect($post);
		}
	}

    /**
     * @param $post int|object|WordpressPost
     * @param array $meta
     * @param string $connectionName
     */
	public function connect($post, $meta = array(), $connectionName = null) {
		$post = WordpressPost::createWordpressPost($post);
		if ($post) {
		    if (!$connectionName) {
                $connectionName = PostTypeManager::get()->generateConnectionName(self::postType(), $post->post_type);
            }
			/** @var \P2P_Directed_Connection_Type $connectionType */
			$connectionType = p2p_type($connectionName);
			if ($connectionType) {
				$p2pId = $connectionType->connect($this->ID, $post->ID);
				foreach ($meta as $key=>$value) {
					p2p_update_meta($p2pId, $key, $value);
				}
			}
		}
	}

    /**
     * @param string|string[] $targetPostType e.g. post, event - the type of connected post(s) you want
     * @param bool $single - just return the first/only post?
     * @param array $queryArgs - augment or overwrite the default parameters for the WP_Query
     * @param bool $hierarchical - if this is true the the function will return any post that is connected to this post *or any of its descendants*
     * @param string|string[] $connectionName If specified, only this connection name is used to find the connected posts (defaults to any/all connections to $targetPostType)
     * @return null|OowpQuery|WordpressPost
     */
	public function connected($targetPostType, $single = false, $queryArgs = array(), $hierarchical = false, $connectionName = null)
	{
		$toReturn = null;
		if (function_exists('p2p_register_connection_type')) {
			if(!is_array($targetPostType)) {
				$targetPostType = array($targetPostType);
			}
			$manager = PostTypeManager::get();
			$postType = self::postType();

			if (!$connectionName) {
                $connectionName = $manager->getConnectionNames($postType, $targetPostType);
            } else if (!is_array($connectionName)) {
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

			$queryArgs   = array_merge($defaults, $queryArgs);
			$result = new OowpQuery($queryArgs);

			if ($hierarchical) { //filter out any duplicate posts
				$post_ids = array();
				foreach($result->posts as $i => $post){
					if(in_array($post->ID, $post_ids)) {
						unset($result->posts[$i]);
					}

					$post_ids[] = $post->ID;
				}
			}

			$toReturn = $single ? null : $result;
			if (!$single) {
				$toReturn = $result;
			} else if ($result && $result->posts) {
				$toReturn = $result->posts[0];
			}
		}

		return $toReturn;
	}

	static function walkTree($p, &$current_descendants = array())
	{
		$current_descendants = array_merge($p->children, $current_descendants);
		foreach ($p->children as $child) {
			self::walkTree($child, $current_descendants);
		}

		return $current_descendants;

	}

	function getDescendants()
	{
		$posts = self::fetchAll();
		$keyed = array();
		foreach ($posts as $post) {
			$keyed[$post->ID] = $post;
			$keyed[$post->ID]->children = array();
		}
		unset($posts);
		foreach ($keyed as $post) { /* This is all a bit complicated but it works */
			if ($post->post_parent) {
				$keyed[$post->post_parent]->children[] = $post;
			}
		}

		$p = $keyed[$this->ID];
		$descendants = static::walkTree($p);
		return $descendants;
	}

	function getDescendantIds()
	{
		$ids = array();
		foreach ($this->getDescendants() as $d) {
			$ids[] = $d->ID;
		}
		return $ids;
	}

	public function allMetadata() {
		return get_metadata('post', $this->ID);
	}

	/**
	 * Gets the metadata (custom fields) for the post
	 * @param string $name
	 * @param bool $single
	 * @return array|string
	 */
	public function metadata($name, $single = true) {
		$meta = null;
		if (function_exists('get_field')) {
			$meta = get_field($name, $this->ID);
			// if not found by acf, then may not be an acf-configured field, so fall back on normal wp method
			if ($meta === false) {
				$fieldObj = get_field_object($name, $this->ID);
				if (!$fieldObj || !$fieldObj['key']) {
					$meta = get_post_meta($this->ID, $name, $single);
				}
			}
		} else {
			$meta = get_post_meta($this->ID, $name, $single);
		}
		if (!$single && !$meta) {
			$meta = array(); // ensure return type is an array
		}
		return $meta;
	}

	/**
	 * Sets the metadata with the given key for the post
	 * @param string $key
	 * @param mixed $value
	 */
	public function setMetadata($key, $value)
	{
		if (function_exists('update_field')) {
			update_field($key, $value, $this->ID);
		} else {
			update_post_meta($this->ID, $key, $value);
		}
	}

	/**
	 * Deletes the metadata with the given key for the post
	 * @param string $key
	 */
	public function deleteMetadata($key)
	{
		if (function_exists('delete_field')) {
			delete_field($key, $this->ID);
		} else {
			delete_post_meta($this->ID, $key);
		}
	}

	/***************************************************************************************************************************************
	 *																																	   *
	 *																  TEMPLATE HELPERS													   *
	 *																																	   *
	 ***************************************************************************************************************************************/

	public function title()
	{
		return apply_filters('the_title', $this->post_title, $this->ID);
	}

	public function content()
	{
		return apply_filters('the_content', $this->post_content);
	}

	public function date($format = 'd M Y')
	{
		return date($format, $this->timestamp());
	}

	public function modifiedDate($format = 'd M Y') {
		return date($format, strtotime($this->post_modified));
	}

	/**
	 * @return WordpressPost|null Get parent of post (or post type)
	 */
	public function parent()
  {
    $parentId = $this->post_parent;
    if (!$parentId) {
      $parentId = static::postTypeParentId();
    }
    $parentSlug = static::postTypeParentSlug();
		if (empty($parentId) && empty($parentSlug)) {
			return null;
		}

		$parent = $this->getCacheValue();
		if (!$parent) {
		  $parent = $parentId ? WordpressPost::fetchById($parentId) : WordpressPost::fetchBySlug($parentSlug);
      $this->setCacheValue($parent);
    }
    return $parent;
	}

	/**
   * If the parent of a hierarchical post type is a page, for example, this needs to be set to that ID.
   * Is mutually exclusive with postTypeParentSlug (this takes priority)
	 * @return int The ID of the parent post for this post type.
	 */
	public static function postTypeParentId()
  {
		return 0;
	}

	/**
   * If the parent of a hierarchical post type is a page, for example, this needs to be set to that slug
   * Is mutually exclusive with postTypeParentId (that takes priority)
	 * @return string The slug of the parent post for this post type.
	 */
	public static function postTypeParentSlug()
  {
		return '';
	}

	/**
	 * Traverses up the getParent() hierarchy until finding one with no parent, which is returned
	 */
	public function getRoot() {
		$parent = $this->parent();
		if ($parent) {
			return $parent->getRoot();
		}
		return $this;
	}

	public function timestamp()
	{
		return strtotime($this->post_date);
	}

	function excerpt($chars = 400, $stuff = null) {
		(!empty($stuff) ?: $stuff = $this->content());
		$content = str_replace("<!--more-->", '<span id="more-1"></span>', $stuff);
		//try to split on more link
		$parts = preg_split('|<span id="more-\d+"></span>|i', $content);
		$content = $parts[0];
		$content = strip_tags($content);
		$excerpt = '';
		$sentences = array_filter(explode(" ", $content));
		if($sentences){
			foreach($sentences as $sentence){
				if((strlen($excerpt) + strlen($sentence)) < $chars && $sentence){
					$excerpt .= $sentence." ";
				}else{
					break;
				}
			}
		}

		if(!$excerpt){
			$words = array_filter(explode(" ", $content));
			if($words){
				foreach($words as $word){
					if((strlen($excerpt) + strlen($word)) < $chars && $word){
						$excerpt .= $word." ";
					}else{
						break;
					}
				}
			}
		}

		$excerpt = trim(str_replace('&nbsp;', ' ',$excerpt));
		if(preg_match('%\w|,|:%i', substr($excerpt, -1))) {
			$excerpt = $excerpt . "...";
		}

		return ($excerpt);
	}

	public function permalink($leaveName = false) {
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
	 * @param array $queryArgs
	 * @return WordpressPost[]|OowpQuery
	 */
	public function children($queryArgs = array())
	{
		$posts = array();
		$postTypes = (array_key_exists ('post_type', $queryArgs) ? $queryArgs['post_type'] : 'any');
		unset($queryArgs['post_type']);
		if (!is_array($postTypes)) {
			$postTypes = array($postTypes);
		}
		$manager = PostTypeManager::get();
		foreach($this->childPostClassNames() as $className){
			foreach($postTypes as $postType){
				if($postType == 'any' || ($postType != 'none' && $manager->getClassName($postType) == $className)) {
					$posts = array_merge($posts, $className::fetchRoots($queryArgs)->posts);
				}
			}
		}
		$defaults = array('post_parent' => $this->ID);
		$queryArgs = wp_parse_args($queryArgs, $defaults);
		$children = static::fetchAll($queryArgs);
		$children->posts = array_merge($children->posts, $posts);
		$children->post_count = count($children->posts);
		return $children;
	}

	/**
	 * @return array Class names of WordpressPost types having this post as their parent
	 */
	public function childPostClassNames()
	{
		$manager = PostTypeManager::get();
		$names = array();
		foreach ($manager->getPostTypes() as $postType) {
			$class = $manager->getClassName($postType);
			if ($class::postTypeParentId() == $this->ID || $class::postTypeParentSlug() == $this->post_name) {
				$names[] = $class;
			}
		}
		return $names;
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
		$names = array();
		foreach(self::connectedPostTypes() as $postType){
			$names[] = $manager->getClassName($postType);
		}
		return $names;
	}

    /**
     * Executes a wordpress function, setting $this as the global $post first, then resets the global post data.
     * Expects the first argument to be the function, followed by any arguments
     * @return mixed
     */
    protected function callGlobalPost()
    {
        global $post;
        $prevPost = $post;

        // Get requested WordPress function and arguments
        $args = func_get_args();
        $callback = array_shift($args);

        // Set up global variables to support WP function execution
        $post = $this->get_post();
        setup_postdata($post);

        // Call the WordPress function
        $returnVal = call_user_func_array($callback, $args);

        // Restore original global variables
        $post = $prevPost;
        wp_reset_postdata();

        return $returnVal;
    }

	public function wp_author()
	{
		return $this->callGlobalPost('get_the_author');
	}

	/**
	 * @return string the Robots meta tag, should be NOINDEX, NOFOLLOW for some post types
	 */
	public function robots(){
		return "";
	}

	/**
	 * Gets the url for editing this post. Returns blank if $requireLoggedIn is true and the logged-in user doesn't have the right permissions
	 * @param $requireLoggedIn
	 * @return string
	 */
	public function editUrl($requireLoggedIn = false) {
		$url = get_edit_post_link($this->ID, '');
		if (!$url && !$requireLoggedIn) {
			$post_type_object = get_post_type_object(static::postType());
			$url = admin_url( sprintf($post_type_object->_edit_link . '&action=edit', $this->ID));
		}
		return $url;
	}

	/**
	 * Use this with attachment posts to convert the front page of a PDF to a PNG, and create a corresponding attachment.
	 * Typical usage (requires project to define xxAttachment class):
	 * add_action('add_attachment', function($id) {
	 *     //use constructor rather than factory to get around auto-draft post_status issue
	 *     $attachment = new xxAttachment($id);
	 *     $attachment->generatePdfImage();
	 * });
	 * IMPORTANT!!! in php-fpm.conf, the env[PATH] = /usr/local/bin:/usr/bin:/bin needs to be uncommented for this to work
	 * @param string $extension
	 * @param string $namePrefix
	 * @param bool $logDebug
	 */
	public function generatePdfImage($extension = 'png', $namePrefix = 'pdf-image-', $logDebug = false) {
		$debug = @fopen(get_stylesheet_directory() . '/debug.txt', 'a');
		$log = function($message, $force = false) use ($debug, $logDebug) {
			if ($debug && ($logDebug || $force)) {
				@fwrite($debug, '[' . date('Y-m-d H:i:s') . "]: $message\n");
			}
		};
		$log('checking for suitability (' . $this->ID . ", $extension, $namePrefix)");
		// IMAGEMAGICK_CONVERT should be defined in wp-config.php
		if (defined('IMAGEMAGICK_CONVERT') && IMAGEMAGICK_CONVERT && $this->post_mime_type == 'application/pdf') {
			$log('attempting conversion');

			$sourceFile = get_attached_file($this->ID);
			$targetFile = str_replace('.pdf', '.' . $extension, $sourceFile);

			// Converted image will have a fixed size (-extent), centred (-gravity), with the aspect ratio respected (-thumbnail), and
			// excess space filled with transparent colour (-background)
			$size = '260x310';
			$args = array(
				'-density 96',
				'-quality 85',
				'-thumbnail ' . $size,
//				'-extent ' . $size,
				'-gravity center',
				'-background transparent',
				escapeshellarg($sourceFile . '[0]'),
				escapeshellarg($targetFile)
			);
			$cmd = IMAGEMAGICK_CONVERT . ' ' . implode(' ', $args) . ' 2>&1';
			$out = exec($cmd, $output, $returnVar);

			$log($cmd);
			// if the convert fails, log the output
			if ($returnVar != 0) {
				$log('conversion failed', true);
				$log('out: ' . $out, true);
				$log('output: ' . print_r($output, true), true);
				$log('returnVar: ' . $returnVar, true);
			} else {
				//create wordpress attachment for thumbnail image
				$attachmentSlug = $namePrefix . $this->ID;
				$log('creating attachment: ' . $attachmentSlug);
				$targetAttachment = self::fetchBySlug($attachmentSlug);
				if ($targetAttachment) {
					$log('deleting existing attachment: ' . $targetAttachment->ID);
					wp_delete_attachment($targetAttachment->ID);
				}
				$id = wp_insert_attachment(array(
					'post_title' => '[Thumb] ' . $this->title(),
					'post_name' => $attachmentSlug,
					'post_content' => '',
					'post_mime_type' => 'image/' . $extension
				), $targetFile);
				$log('created new attachment: ' . print_r($id, true));
			}
		} else {
			if (!defined('IMAGEMAGICK_CONVERT')) {
				$log('ignoring: IMAGEMAGICK_CONVERT not defined');
			} else {
				$log('ignoring: post type is ' . $this->post_mime_type);
			}
		}
		@fclose($debug);
	}

#endregion

#region HTML Template helpers

	public function htmlLink($attrs = array())
	{
		$attrString = self::getAttributeString($attrs);
		return '<a href="' . $this->permalink() . '" ' . $attrString . '>' . $this->title() . "</a>";
	}

	/**
	 * @static turns and array of key=>value attibutes into html string
	 * @param array $attrs  key=>value attributes
	 * @return string html for including in an element
	 */
	public static function getAttributeString($attrs){
		$attributeString = '';
		foreach($attrs as $key => $value){
			$attributeString .= " $key='$value' ";
		}
		return $attributeString;
	}

	/**
	 * @return WordpressTheme
	 */
	public static function theme() {
		return WordpressTheme::getInstance();
	}

	function htmlAuthorLink()
	{
		return $this->callGlobalPost('get_the_author_link');
	}

	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPage() {
		$x = WordpressPost::getQueriedObject();
		return (isset($x) && $x->ID == $this->ID);
	}

	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPageParent() {
		$x = WordpressPost::getQueriedObject();
		return (isset($x) && ($x->post_parent == $this->ID || $x->postTypeParentId() == $this->ID || $x->postTypeParentSlug() == $this->post_name));
	}

	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPageAncestor() {
		$x = WordpressPost::getQueriedObject();
		while (isset($x) && $x) {
			if ($x->ID == $this->ID) {
				return true;
			}
			$x = $x->parent();
		}
		return false;
	}


    protected function featuredImageAttachmentId() {
        $image = $this->metadata('featured_image', true) ?: $this->metadata('image', true);

        if ($image) {
            if (is_numeric($image)) {
                return $image;
            }
            return $image['id'];
        }
        return false;
    }

	public function featuredImageUrl($image_size = 'thumbnail'){
		$image = wp_get_attachment_image_src($this->featuredImageAttachmentId(), $image_size);
		return $image ? $image[0] : null;
	}

	public function featuredImage($size = 'thumbnail', $attrs = array()){
		return wp_get_attachment_image($this->featuredImageAttachmentId(), $size, 0, $attrs);
	}

	/**
	 * Gets the list of elements that comprise a breadcrumb trail
	 */
	function breadcrumbs(){
		$ancestors = array($this->title());
		$current = $this;
		while($parent = $current->parent()){
			$ancestors[] = $parent->htmlLink();
			$current = $parent;
		}
		$home = self::fetchHomepage();
		if ($home && $this->ID != $home->ID) {
			$ancestors[] = $home->htmlLink();
		}
		return array_reverse($ancestors);
	}

	/**
	 * @return OowpQuery
	 */
	public function attachments(){
		$queryArgs = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'inherit', 'post_parent' => $this->ID );
		return new OowpQuery($queryArgs);
	}

#endregion

#region Static functions

	/**
	 * @static
	 * Called by register(), for registering this post type
	 * @return mixed array of arguments used by register_post
	 */
	static function getRegistrationArgs() {
	    return array(
            'labels' => AdminUtils::generateLabels(static::friendlyName(), static::friendlyNamePlural()),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array(
                'slug' => static::postType(),
                'with_front' => false
            ),
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => array(
                'title',
                'editor',
                'revisions',
            )
        );
	}

	/**
	 * Use this in combination with getCustomAdminColumnValue to add custom columns to the wp admin interface for the post.
	 * @param $helper ArrayHelper Contains the default columns
	 * @static
	 */
	static function addCustomAdminColumns(ArrayHelper $helper) { /* do nothing */ }

	/**
	 * Use this in combination with addCustomAdminColumns to get the column value for a post
	 * @param string $column The name of the column, as given in addCustomAdminColumns
	 * @return string
	 */
	function getCustomAdminColumnValue($column)
	{
		return '';
	}

	/**
	 * @static
	 * Gets the queried object (i.e. the post/page currently being viewed)
	 * @return null|WordpressPost
	 */
	static function getQueriedObject() {
		global $ooQueriedObject;
		if (!isset($ooQueriedObject)) {
			global $wp_the_query;
			$id = $wp_the_query->get_queried_object_id();
			$ooQueriedObject = $id ? WordpressPost::fetchById($id) : null;
		}
		return $ooQueriedObject;
	}

    /**
     * @static Creates a p2p connection to another post type
     * @param string $targetPostType The post_type of the post type you want to connect to
     * @param array $parameters These can overwrite the defaults. Do not specify connection_name, use $connectionName instead
     * @param string $connectionName
     * @return mixed
     */
	static function registerConnection($targetPostType, $parameters = array(), $connectionName = null)
	{
		return PostTypeManager::get()->registerConnection(self::postType(), $targetPostType, $parameters, $connectionName);
	}

	/**
	 * Factory method for creating a post of the appropriate WordpressPost subclass, for the given data
	 * @static
	 * @param object|int $data
	 * @return WordpressPost|null
	 */
	public static function createWordpressPost($data = null) {
		if ($data) {
			if ($data instanceof WordpressPost) {
				return $data;
			}
			$postData = self::getPostObject($data);
			if ($postData) {
				$className = PostTypeManager::get()->getClassName($postData->post_type);
                if (!$className) {
                    $className = 'Outlandish\Wordpress\Oowp\PostTypes\MiscPost';
                }
				if ($postData instanceof $className) {
					return $postData;
				} else {
					return new $className($postData);
				}
			}
		}
		return null;
	}

	/**
	 * Factory method for creating a post of the appropriate WordpressPost subclass, for the given post ID
	 * @static
	 * @param $ids int|int[]
	 * @return WordpressPost|OowpQuery|null
	 */
	public static function fetchById($ids) {
		if (is_array($ids) && $ids){
			return new OowpQuery(array('post__in' => $ids));
		}elseif($ids){
			return static::fetchOne(array('p' => $ids));
		}else{
			throw new \Exception("no IDs supplied to WordpressPost::fetchById()");
		}
	}

	public static function fetchBySlug($slug){
		return static::fetchOne(array('name' => $slug));
	}

	/**
	 * @static
	 * @param array $queryArgs - accepts a wp_query $queryArgs array which overwrites the defaults
	 * @return OowpQuery
	 */
	public static function fetchAll($queryArgs = array())
	{
		$defaults = array(
			'post_type' => static::getSelfPostTypeConstraint()
		);
		if (static::isHierarchical()) {
			$defaults['orderby'] = 'menu_order';
			$defaults['order'] = 'asc';
		}

		$queryArgs = wp_parse_args($queryArgs, $defaults);
		$query	= new OowpQuery($queryArgs);

		return $query;
	}

	/**
	 * @deprecated
	 */
	static function fetchAllQuery($queryArgs = array())
	{
		return static::fetchAll($queryArgs);
	}

	/**
	 * @static
	 * @return null|WordpressPost
	 */
	static function fetchHomepage() {
		$key = 'homepage';
		if (!array_key_exists($key, WordpressPost::$_staticCache)) {
			$id = get_option('page_on_front');
			WordpressPost::$_staticCache[$key] = $id ? self::fetchById($id) : null;
		}
		return WordpressPost::$_staticCache[$key];
	}

	/**
	 * @return bool true if this is the site homepage
	 */
	public function isHomepage() {
		return $this->ID == get_option('page_on_front');
	}

	/**
	 * Return the first post matching the arguments
	 * @static
	 * @param $queryArgs
	 * @return null|WordpressPost
	 */
	static function fetchOne($queryArgs)
	{
        $queryArgs['posts_per_page'] = 1; // Force-override this rather than only setting a default.
        $defaults = array(
            'post_type' => static::getSelfPostTypeConstraint()
        );
        $queryArgs = wp_parse_args($queryArgs, $defaults);

		$query = new OowpQuery($queryArgs);
		return $query->posts ? $query->post : null;
	}

	/**
	 * @static Returns the roots of this post type (i.e those whose post_parent is self::postTypeParentId)
	 * @param array $queryArgs
	 * @return OowpQuery
	 */
	static function fetchRoots($queryArgs = array())
	{
		#todo perhaps the post_parent should be set properly in the database
//		$queryArgs['post_parent'] = static::postTypeParentId();
		$queryArgs['post_parent'] = self::postTypeParentId();
		return static::fetchAll($queryArgs);
	}


	/**
	 * @static
	 * @param null $postType
	 * @return bool Whether or not the post type is declared as hierarchical
	 */
	static function isHierarchical($postType = null) {
		if (!$postType) {
			$postType = static::postType();
		}
		return is_post_type_hierarchical($postType);
	}

    private static function getSelfPostTypeConstraint()
    {
        // If `get*()` methods are called on abstract post classes directly (not a registered post subclass), do not
        // constrain the type of posts returned unless specified.
        if (!PostTypeManager::get()->postClassIsRegistered(static::class)) {
            return 'any';
        }

        // Otherwise, default to constraining to the type associated with the class on which the
        // method was invoked.
        return static::postType();
    }

#endregion


}
