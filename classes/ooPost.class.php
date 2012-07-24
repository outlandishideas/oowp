<?php
require_once('ooQuery.class.php');

/**
 * This class is a placeholder for all functions which are shared across all post types, and across all sites.
 * It should be extended for each site by e.g. oiPost or irrPost, which should in turn be extended by individual post types e.g. irrEvent, irrShopItem
 */
class ooPost
{

	protected $_cache = array();

#region Getters, Setters, Construct, Init

	/**
	 * @param $data int | array | object
	 */
	public function __construct($data)
	{
		//Make sure it's an object

		$thePost = $this->getPostObject($data);

		foreach ($thePost as $key => $property) {
			$this->$key = $property;
		}
	}

	public static function getPostObject($data)
	{
		if (is_array($data)) {
			return (object)$data;
		} else if (is_object($data)) {
			return $data;
		} else if (is_integer($data)) {
			return get_post($data);
		} else {
			global $post;
			return $post;
		}
	}

	/**
	 * @static
	 * Should be run with the wordpress init hook
	 */
	public final static function init()
	{
		static::register();
	}

	/**
	 * @static
	 * Called after all oowp classes have been registered
	 */
	public static function postRegistration() { /* do nothing by default */ }

	/**
	 * @param $name
	 * @param $args
	 * @return mixed
	 * @throws Exception
	 */
	public function __call($name, $args)
	{
		if (function_exists($name)) {
			oofp('Using default wordpress function ' . $name . ' and global $post');
			global $post;
			$post = $this;
			setup_postdata($this);
			return call_user_func_array($name, $args);
			wp_reset_postdata();
		} elseif (function_exists("wp_" . $name)) {
			$name = "wp_" . $name;
			oofp('Using default wordpress function wp_' . $name . ' and global $post');
			global $post;
			$post = $this;
			setup_postdata($this);
			return call_user_func_array($name, $args);
			wp_reset_postdata();
		} else {
			trigger_error('Attempt to call non existenty method ' . $name . ' on class ' . get_class($this));
			//throw new Exception(sprintf('The required method "%s" does not exist for %s', $name, get_class($this)));
		}
	}

	/**
	 * Returns the name of the function that called whatever called the caller :)
	 * e.g. if theFunction() called theOtherFunction(), theOtherFunction() could call getCaller(), which
	 * would return 'theFunction'
	 * @param null $function Don't supply this
	 * @param int $diff Don't supply this
	 * @return string
	 */
	protected function getCaller($function = null, $diff = 1) {
		if (!$function) {
			return $this->getCaller(__FUNCTION__, $diff+2);
		}

		$stack = debug_backtrace();
		$stackSize = count($stack);

		$caller = '';
		for ($i = 0; $i < $stackSize; $i++) {
			if ($stack[$i]['function'] == $function && ($i + $diff) < $stackSize) {
				$caller = $stack[$i + $diff]['function'];
				break;
			}
		}

		return $caller;
	}

	/**
	 * Gets the cached value for the function that called this
	 * @return mixed
	 */
	protected function getCacheValue() {
		$functionName = $this->getCaller();
		return (array_key_exists($functionName, $this->_cache) ? $this->_cache[$functionName] : null);
	}

	/**
	 * Sets and returns the cached value for the function that called this
	 * @param $value
	 * @return mixed
	 */
	protected function setCacheValue($value) {
		$this->_cache[$this->getCaller()] = $value;
		return $value;
	}

#endregion

#region Default getters

	protected static $postTypes = array();

	/**
	 * @static
	 * @return string - the post name of this class derived from the classname
	 */
	public static function postType()
	{
		$class = get_called_class();
		if (!array_key_exists($class, static::$postTypes)) {
			$postType = null;
			if (preg_match('/([A-Z].*)/m', $class, $regs)) {
				$match = $regs[1];
				$postType = lcfirst(from_camel_case($match));
			}
			static::$postTypes[$class] = $postType;
		}

		$postType = static::$postTypes[$class];
		if (!$postType) {
			die('Invalid post type');
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
	 * Gets all terms associated with this post, indexed by their taxonomy.
	 * @param null $taxonomies - restrict to only one or more taxonomies
	 * @param bool $includeEmpty - return empty
	 * @return array - term objects
	 */
	public function terms($taxonomies = null, $includeEmpty = false)
	{
		if (!$taxonomies) {
			$taxonomies = ooTaxonomy::fetchAllNames();
		} else if (!is_array($taxonomies)) {
			$taxonomies = array($taxonomies);
		}
		$terms = array();
		foreach ($taxonomies as $taxonomy) {
			$currentTerms = wp_get_post_terms($this->ID, $taxonomy);
			if ($currentTerms || $includeEmpty) {
				foreach ($currentTerms as $term) {
					$terms[] = ooTerm::fetch($term);

				}
			}
		}
		return $terms;
	}


	/**
	 * @param $targetPostType string e.g. post, event - the type of post you want to connect to
	 * @param bool $single - just return the first/only post?
	 * @param array $args - augment or overwrite the default parameters for the WP_Query
	 * @param bool $hierarchical - if this is true the the function will return any post that is connected to this post *or any of its descendants*
	 * @return array
	 */
	protected function getConnected($targetPostType, $single = false, $args = array(), $hierarchical = false)
	{
		if (function_exists('p2p_register_connection_type')) {
			$postType = $this::postType();
            if(!is_array($targetPostType)) {
                $targetPostType = array($targetPostType);
            }
            $connection_name = array();
            foreach ($targetPostType as $targetType) {
                $types = array($targetType, $postType);
                sort($types);
                $connection_name[] = implode('_', $types);
            }

			#todo optimisation: check to see if this post type is hierarchical first
			if ($hierarchical) {
				$connected_items = array_merge($this->getDescendantIds(), array($this->ID));
			} else {
				$connected_items = $this->ID;
			}

			$defaults = array(
				'connected_type'  => $connection_name,
				'connected_items' => $connected_items,
				'post_type'       => $targetPostType,
			);

			$args   = array_merge($defaults, $args);
			$result = self::fetchAll($args);

            $toReturn = $single ? null : $result;
            if ($result && $result->posts) {
				$toReturn = $single ? $result->posts[0] : $result;
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
			$keyed[$post->ID]           = $post;
			$keyed[$post->ID]->children = array();
		}
		unset($posts);
		foreach ($keyed as $post) { /* This is all a bit complicated but it works */
			if ($post->post_parent)
				$keyed[$post->post_parent]->children[] = $post;
		}

		$p           = $keyed[$this->ID];
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

	/**
	 * Gets the metadata (custom fields) for the post
	 * @param $name
	 * @param bool $single
	 * @return array
	 */
	public function getMetadata($name, $single = false) {
		$meta = null;
		if (function_exists('get_field')) {
			$meta = get_field($name, $this->ID);
		}
		if (!$meta) {
			$meta = get_post_meta($this->ID, $name, $single);
		}
		return $meta;
	}

	/***************************************************************************************************************************************
	 *                                                                                                                                       *
	 *                                                                  TEMPLATE HELPERS                                                       *
	 *                                                                                                                                       *
	 ***************************************************************************************************************************************/

	public function title()
	{
		return apply_filters('the_title', $this->post_title);
	}

	public function content()
	{
		return apply_filters('the_content', $this->post_content);
	}

	public function date($format = 'd M Y')
	{
		return date($format, $this->timestamp());
		//		return apply_filters('the_date', $this->post_date);
	}

	public function getParent() {
		$parentId = $this->isHierarchical() ? $this->post_parent : static::postTypeParentId();
		return $this->getCacheValue() ?: $this->setCacheValue(
			!empty($parentId) ? ooPost::fetch($parentId) : null
		);
	}

	/**
	 * @static
	 * @return int returns the root parent type for posts.
	 * If parent of a hierchical post type is a page, for example, this needs to be set to that ID
	 */
	public static function postTypeParentId(){
		return 0;
	}

	/**
	 * Traverses up the getParent() hierarchy until finding one with no parent, which is returned
	 */
	public function getRoot() {
		$parent = $this->getParent();
		if ($parent) {
			return $parent->getRoot();
		}
		return $this;
	}

	public function timestamp()
	{
		return strtotime($this->post_date);
	}

	public function excerpt()
	{
		return $this->callGlobalPost('get_the_excerpt');
	}

	public function permalink()
	{
		return get_permalink($this);
	}

	/**
	 * Fetches all posts (of any post_type) whose post_parent is this post, as well as
	 * the root posts of any post_types whose declared postTypeParentId is this post
	 * @return array
	 */
	public function children()
	{
		global $_registeredPostClasses;
		$posts = array();
		foreach($_registeredPostClasses as $class){
			if($class::postTypeParentId() == $this->ID){
				$posts = array_merge($posts, $class::fetchRoots()->posts);
			}
		}
		$children = static::fetchAll(array('post_parent' => $this->ID));
		$children->posts = array_merge($children->posts, $posts);
		return $children;
	}

	/**
	 * Executes a wordpress function, setting $this as the global $post first, then resets the global post data.
	 * Expects the first argument to be the function, followed by any arguments
	 * @return mixed
	 */
	protected function callGlobalPost()
	{
		$args     = func_get_args();
		$callback = array_shift($args);
		global $post;
		$post = $this;
		setup_postdata($this);
		$returnVal = call_user_func_array($callback, $args);
		wp_reset_postdata();
		return $returnVal;
	}

	public function author()
	{
		return $this->callGlobalPost('get_the_author');
	}


#endregion

#region HTML Template helpers

	public function htmlLink()
	{
		return "<a href='" . $this->permalink() . "'>" . $this->title() . "</a>";
	}

	protected static function htmlList($items)
	{
		$links = array();
		foreach ($items as $term) {
			$links[] = $term->htmlLink();
		}
		return implode(', ', $links);
	}

	/**
	 * @static
	 * Prints each of the post_type roots using the 'menuitem' partial
	 * @param array $args
	 */
	public static function printMenuItems($args = array()){
		if(!isset($args['post_parent'])){
			$posts = static::fetchRoots($args);
		}else{
			$args['depth'] = 1;
			$posts = static::fetchAll($args);
		}

		foreach($posts as $post){
			$post->printMenuItem($args);
		}
	}

	// functions for printing with each of the provided partial files
	public function printSidebar() { $this->printPartial('sidebar'); }
	public function printMain() { $this->printPartial('main'); }
	public function printItem() { $this->printPartial('item'); }
	public function printMenuItem($args = array()) {
		$args['max_depth'] = isset($args['max_depth'])? $args['max_depth'] : 0;
		$args['current_depth'] = isset($args['current_depth'])? $args['current_depth'] : 0;
		$this->printPartial('menuitem', $args);
	}

	/**
	 * Prints the partial into an html string, which is returned
	 * @param $partialType
	 * @return string
	 */
	public final function getPartial($partialType)
	{
		ob_start();
		$this->printPartial($partialType);
		$html = ob_get_contents();
		ob_end_flush();
		return $html;
	}

	/**
	 * looks for $partialType-$post_type.php, then $partialType.php in the partials directory of
	 * the theme, then the plugin
	 * @param $partialType  - e.g. main,  item, promo, etc
	 * @param array $args To be used by the partial file
	 */
	public function printPartial($partialType, $args = array())
	{
		// look in the theme directory, then plugin directory
		$places = array(get_stylesheet_directory() . '/partials', dirname(__FILE__) . "/../partials");
		$paths  = array();

		$specific = array();
		$nonspecific = array();
		foreach ($places as $path) {
			if (file_exists($path)) {
				$fh = opendir($path);
				while (false !== ($entry = readdir($fh))) {
					$paths[] = $path . DIRECTORY_SEPARATOR . $entry;
					if (strpos($entry, $partialType) === 0) {
						// if there is a file that contains the post name too, make that a priority for selection
						$postTypeStart = strpos($entry, '-');
						$postTypeEnd = strpos($entry, '.php');
						if ($postTypeStart > 0) {
							// split everything after the '-' by valid separators: ',', '|', '+' or ' '
							$postTypes = preg_split('/[\s,|\+]+/', substr($entry, $postTypeStart+1, $postTypeEnd - $postTypeStart - 1));
							if (in_array($this->postType(), $postTypes)) {
								$specific[] = $path . DIRECTORY_SEPARATOR . $entry;
								break;
							}
						} else {
							$nonspecific[] = $path . DIRECTORY_SEPARATOR . $entry;
						}
					}
				}
				closedir($fh);
			}
		}

		// pick the first specific, or the first non-specific to display
		$match = ($specific ? $specific[0] : ($nonspecific ? $nonspecific[0] : null));
		if ($match) {
			$post = $this;
			if (WP_DEBUG) print "\n\n<!--start $match start-->\n";
			include($match);
			if (WP_DEBUG) print "\n<!--end $match end-->\n\n";
		} else {
			// show an error message
			?>
			<div class="oowp-error">
				<span class="oowp-post-type"><?php echo $this->postType(); ?></span>: <span class="oowp-post-id"><?php echo $this->ID; ?></span>
				<div class="oowp-error-message">Partial '<?php echo $partialType; ?>' not found</div>
				<!-- <?php print_r($paths); ?> -->
			</div>
			<?php
//  		throw new Exception(sprintf("Partial $partialType not found", $paths, get_class($this)));
		}
	}

	/***
	 * Alias for printPartial() - only used for turning this post into html
	 *
	 * @param $partialType - string
	 */
	public function printAsHtml($partialType)
	{
		$this->printPartial($partialType);
	}

	public function toHtml($partialType)
	{
		$this->getPartial($partialType);
	}

	function htmlAuthorLink()
	{
		return $this->callGlobalPost('get_the_author_link');
	}

	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPage() {
		$x = ooPost::getQueriedObject();
		if (isset($x) && $x->ID == $this->ID) return true;

		return false;
	}
	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPageParent() {
		$x = ooPost::getQueriedObject();
		if (isset($x) && ($x->post_parent == $this->ID || $x->postTypeParentId() == $this->ID)) return true;

		return false;
	}
	/**
	 * @return bool true if this is an ancestor of the page currently being viewed
	 */
	public function isCurrentPageAncestor() {
		$x = ooPost::getQueriedObject();
		while (isset($x) && $x) {
			if ($x->ID == $this->ID) return true;
			$x = $x->getParent();
		}
		return false;
	}


	protected function featuredImageAttachmentId() {
		return $this->getMetadata('featured_image', true) ?: $this->getMetadata('image', true);
	}

	public function featuredImageUrl($image_size = 'thumbnail'){
		$image = wp_get_attachment_image_src($this->featuredImageAttachmentId(), $image_size);
		return $image[0];
	}

	public function featuredImage($size = 'thumbnail', $attrs = array()){
		return wp_get_attachment_image($this->featuredImageAttachmentId(), $size, 0, $attrs);
	}

	function breadcrumbs(){
		$delimiter = '&raquo;';
		$ancestors = array($this->title());
		$current = $this;
		while($parent = $current->getParent()){
			$ancestors[] = $parent->htmlLink();
			$current = $parent;
		}
		if($this->ID != HOME_PAGE_ID){
			$ancestors[] = ooPost::fetch(HOME_PAGE_ID)->htmlLink();
		}
		array_reverse($ancestors);
		print implode(" $delimiter ", array_reverse($ancestors));
	}

	public function attachments(){
		$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'inherit', 'post_parent' => $this->ID );
		return self::fetchAll($args);

	}

	protected function listToString($posts, $without_links = false){
		$links = array();
		foreach ( $posts as $item ) {
			$links[] = $without_links ? $item->title() : "<a href='". $item->permalink() ."'>".$item->title()."</a>";
		}

		if(count($links) > 1){
			$a1 = array_pop($links);
			$a2 = array_pop($links);
			$links[] = "$a1 & $a2";
			return implode(', ', $links);
		}elseif($links){
			return $links[0];
		}
	}

#endregion

#region Static functions

	/**
	 * @static
	 * Called by register(), for registering this post type
	 * @param $defaults
	 * @return mixed array of arguments used by register_post
	 */
	static function getRegistrationArgs($defaults) {
		return $defaults;
	}

	/**
	 * @static Registers the post type (if not a built-in type), by calling getRegistrationArgs,
	 * then enables customisation of the admin screens for the post type
	 * @return null|object|WP_Error
	 */
	public static final function register() {
		$postType = static::postType();
		if ($postType == 'page' || $postType == 'post' || $postType == 'attachment' ) {
			$var = null;
		} else {
			$defaults = array(
				'labels'      => oowp_generate_labels(static::friendlyName(), static::friendlyNamePlural()),
				'public'      => true,
				'has_archive' => true,
				'rewrite'     => array('slug'      => $postType,
									   'with_front'=> false),
				'show_ui'     => true,
				'supports'    => array(
					'title',
					'editor',
				)
			);
			$args     = static::getRegistrationArgs($defaults);
			$var      = register_post_type($postType, $args);
		}
		$class = get_called_class();
		add_filter("manage_edit-{$postType}_columns", array($class, 'addCustomAdminColumns'));
		add_action("manage_{$postType}_posts_custom_column", array($class, 'printCustomAdminColumn_internal'), 10, 2);
		add_action('right_now_content_table_end', array($class, 'addRightNowCount'));
		global $_registeredPostClasses;
		$_registeredPostClasses[$postType] = $class;
		return $var;
	}

	/**
	 * @static
	 * append the count(s) to the end of the 'right now' box on the dashboard
	 */
	static function addRightNowCount() {
		$postType = static::postType();
		if ($postType != 'post' && $postType != 'page') {
			$singular = static::friendlyName();
			$plural = static::friendlyNamePlural();

			$numPosts = wp_count_posts($postType);

			oowp_print_right_now_count($numPosts->publish, $postType, $singular, $plural);
			if ($numPosts->pending > 0) {
				oowp_print_right_now_count($numPosts->pending, $postType, $singular . ' Pending', $plural . ' Pending', 'pending');
			}
		}
	}

	/**
	 * @static
	 * Use this in combination with printCustomAdminColumn to add custom columns to the wp admin interface for the post
	 * @param $defaults
	 * @return mixed
	 */
	static function addCustomAdminColumns($defaults) {
		return $defaults;
	}

	/**
	 * @static
	 * This simply calls the non-internal version, after creating an object from the id
	 * @param $column
	 * @param $post_id
	 */
	static final function printCustomAdminColumn_internal($column, $post_id) {
		static::printCustomAdminColumn($column, ooPost::fetch($post_id));
	}

	/**
	 * @static
	 * Use this in combination with addCustomAdminColumns to render the column value for a post
	 * @param $column string The name of the column, as given in addCustomAdminColumns
	 * @param $post ooPost The post (subclass) object
	 */
	static function printCustomAdminColumn($column, $post) {
	}

	/**
	 * @static
	 * Gets the queried object (i.e. the post/page currently being viewed)
	 * @return null|ooPost
	 */
	static function getQueriedObject() {
		global $ooQueriedObject;
		if (!isset($ooQueriedObject)) {
			global $wp_the_query;
			$id = $wp_the_query->get_queried_object_id();
			$ooQueriedObject = $id ? ooPost::fetch($id) : null;
		}
		return $ooQueriedObject;
	}

	/**
	 * @static Creates a p2p connection to another post type
	 * @param $targetPostType - the post_type of the post type you want to connect to
	 * @param array $parameters - these can overwrite the defaults. though if you change the name of the connection you'll need a custom getConnected aswell
	 * @return mixed
	 */
	static function registerConnection($targetPostType, $parameters = array())
	{
		if (!function_exists('p2p_register_connection_type'))
			return;
		$postType = (string)self::postType();
		$types    = array($targetPostType, $postType);
		sort($types);
		$connection_name = implode('_', $types);
		$defaults        = array(
			'name'        => $connection_name,
			'from'        => $types[0],
			'to'          => $types[1],
			'cardinality' => 'many-to-many',
			'reciprocal'  => true
		);
		$parameters      = wp_parse_args($parameters, $defaults);
		p2p_register_connection_type($parameters);
	}

	/**
	 * @static factory class creates a post of the appropriate ooPost subclass, populated with the given data
	 * @param null $data
	 * @return ooPost - an ooPost object or subclass if it exists
	 */
	public static function fetch($data = null)
	{
		// construct an appropriate ooPost (subclass) instance, depending on the post_type of the post
		if ($data) {
			$data      = self::getPostObject($data);
			$className = ooGetClassName($data->post_type, 'ooPost');

			return new $className($data);
		}
		return null;
	}

	/**
	 * @static
	 * @param array $args - accepts a wp_query $args array which overwrites the defaults
	 * @return \WP_Query
	 */
	public static function fetchAll($args = array())
	{
		$defaults = array('post_type'      => static::postType(),
						  'posts_per_page' => -1);
		if (static::isHierarchical()) {
			$defaults['orderby'] = 'menu_order';
			$defaults['order'] = 'asc';
		}
		$args     = wp_parse_args($args, $defaults);
		$query    = new ooWP_Query($args);

		if ($query->query_vars['error']) {
			die('Query error ' . $query->query_vars['error']);
		}

		foreach ($query->posts as $i => $post) {
			$query->posts[$i] = static::fetch($post);
		}

		return $query;
	}

	/**
	 * @deprecated
	 */
	static function fetchAllQuery($args = array())
	{
		return static::fetchAll($args);
	}

	/**
	 * @static Returns the roots of this post type (i.e those whose post_parent is self::postTypeParentId)
	 * @param array $args
	 * @return array
	 */
	static function fetchRoots($args = array())
	{
		$args['post_parent'] = self::postTypeParentId();
		return static::fetchAll($args);
	}



	static function isHierarchical() {
		return is_post_type_hierarchical(static::postType());
	}

#endregion


}


?>
