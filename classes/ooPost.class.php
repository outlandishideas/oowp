<?php
require_once('ooQuery.class.php');

/**
 * This class is a placeholder for all functions which are shared across all post types, and across all sites.
 * It should be extended for each site by e.g. oiPost or irrPost, which should in turn be extended by individual post types e.g. irrEvent, irrShopItem
 */
class ooPost
{

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
	public static function init()
	{
		static::register();
	}

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

#endregion

#region Default getters

	/**
	 * @static
	 * @return string - the post name of this class derived from the classname
	 */
	public static function postName()
	{
		if (preg_match('/([A-Z].*)/m', get_called_class(), $regs)) {
			$match = $regs[1];
			return lcfirst(from_camel_case($match));
		} else {
			die('Invalid post type');
		}
	}

	/**
	 * @static
	 * @return string - the human-friendly name of this class, derived from the post name
	 */
	public static function friendlyName() {
		return ucwords(str_replace('_', ' ', static::postName()));
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
	 * @param $targetPostName string e.g. post, event - the type of post you want to connect to
	 * @param bool $single - just return the first?
	 * @param array $args - augment or overwrite the default parameters for the WP_Query
	 * @param bool $hierarchical - if this is true the the function will return any post that is connected to this post *or any of its descendants*
	 * @return array
	 */
	protected function getConnected($targetPostName, $single = false, $args = array(), $hierarchical = false)
	{
		if (!function_exists('p2p_register_connection_type'))
			return;
		$postName = $this::postName();
		$names    = array($targetPostName, $postName);
		sort($names);
		$connection_name = implode('_', $names);

		#todo optimisation: check to see if this post type is hierarchical first
		if ($hierarchical) {
			$connected_items = array_merge($this->getDescendantIds(), array($this->ID));
		} else {
			$connected_items = $this->ID;
		}

		$defaults = array(
			'connected_type'  => $connection_name,
			'connected_items' => $connected_items,
			'post_type'       => $targetPostName,
		);

		$args   = array_merge($defaults, $args);
		$result = self::fetchAll($args);

		if ($result && $single)
			return $result[0];

		return $result;

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
		$meta = get_post_meta($this->ID, $name, $single);
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
		return (is_post_type_hierarchical($this->postName()) && isset($this->parent_id) ? ooPost::fetch($this->parent_id) : null);
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
		global $post;
		$post = $this;
		setup_postdata($this);
		return apply_filters('the_excerpt', get_the_excerpt());
	}

	public function permalink()
	{
		return get_permalink($this);
	}

	public function children()
	{
		return static::fetchAll(array('post_parent' => $this->ID));
	}

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
		return $this->callGlobalPost(function()
		{
			return get_the_author();
		});
//		global $post;
//		setup_postdata($this);
//		return get_the_author($this);
//		wp_reset_postdata();
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

	public function printSidebar()
	{
		$this->printPartial('sidebar');
	}

	public function printMain()
	{
		$this->printPartial('main');
	}

	public function printItem()
	{
		$this->printPartial('item');
	}


	/** PROTECTED to ensure that it's not used directly in templates but instead used through printMain, printItem, etc. This allows classes to add a custom 'printItem' or 'printSidebar' method so that multiple post types can share a single partial
	 * @param $partialType
	 * @return string
	 */
	protected function getPartial($partialType)
	{
		ob_start();
		$this->printPartial($partialType);
		$html = ob_get_contents();
		ob_end_flush();
		return $html;
	}

	/**
	 * looks for partial-$partialType-$post_type.php the partial-$partialType.php
	 * @param $partialType  - e.g. main,  item, promo, etc
	 */
	protected function printPartial($partialType)
	{
		// look in the stylesheet directory, then plugin directory
		$places = array(get_stylesheet_directory() . '/partials', dirname(__FILE__) . "/../partials");
		$paths  = array();

		$specific = array();
		$nonspecific = array();
		foreach ($places as $path) {
			if (file_exists($path)) {
				$fh = opendir($path);
				while (false !== ($entry = readdir($fh))) {

					if (strpos($entry, $partialType) === 0) {
						// if there is a file that contains the post name too, make that a priority for selection
						$postTypeStart = strpos($entry, '-');
						$postTypeEnd = strpos($entry, '.php');
						if ($postTypeStart > 0) {
							// split everything after the '-' by valid separators: ',', '|', '+' or ' '
							$postNames = preg_split('/[\s,|\+]+/', substr($entry, $postTypeStart+1, $postTypeEnd - $postTypeStart - 1));
							if (in_array($this->postName(), $postNames)) {
								$specific[] = $path . DIRECTORY_SEPARATOR . $entry;
							}
						} else {
							$nonspecific[] = $path . DIRECTORY_SEPARATOR . $entry;
						}
//						break;
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
			return;
		}

		// if it gets to here, show an error message
		?>
		<div class="oowp-error">
			<span class="oowp-post-type"><?php echo $this->postName(); ?></span>: <span class="oowp-post-id"><?php echo $this->ID; ?></span>
			<div class="oowp-error-message">Partial '<?php echo $partialType; ?>' not found</div>
		</div>
		<?php
//		throw new Exception(sprintf("Partial $partialType not found", $paths, get_class($this)));
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
	 * @static
	 *
	 * @return object|the|WP_Error
	 */
	static function register() {
		$postName = static::postName();
		if ($postName == 'page' || $postName == 'post') return;
		$defaults = array(
			'labels'      => oowp_generate_labels(static::friendlyName(), static::friendlyNamePlural()),
			'public'      => true,
			'has_archive' => true,
			'rewrite'     => array('slug'      => $postName,
								   'with_front'=> false),
			'show_ui'     => true,
			'supports'    => array(
				'title',
				'editor',
			)
		);
		$args     = static::getRegistrationArgs($defaults);
		$var      = register_post_type($postName, $args);
		$class = get_called_class();
		add_filter("manage_edit-{$postName}_columns", array($class, 'addCustomAdminColumns'));
		add_action("manage_{$postName}_posts_custom_column", array($class, 'printCustomAdminColumn_internal'), 10, 2);
		add_action('right_now_content_table_end', array($class, 'addRightNowCount'));
		return $var;
	}

	/**
	 * @static
	 * append the count(s) to the end of the 'right now' box on the dashboard
	 */
	static function addRightNowCount() {
		$postName = static::postName();
		$friendlyName = static::friendlyNamePlural();

		$numPosts = wp_count_posts($postName);

		oowp_print_right_now_count($numPosts->publish, $postName, $friendlyName);
		if ($numPosts->pending > 0) {
			oowp_print_right_now_count($numPosts->pending, $postName, $friendlyName . ' Pending', 'pending');
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
	 * @static Creates a p2p connection to another post type
	 * @param $targetPostName - the post_type of the post type you want to connect to
	 * @param array $parameters - these can overwrite the defaults. though if you change the name of the connection you'll need a custom getConnected aswell
	 * @return mixed
	 */
	static function registerConnection($targetPostName, $parameters = array())
	{
		if (!function_exists('p2p_register_connection_type'))
			return;
		$postName = (string)self::postName();
		$names    = array($targetPostName, $postName);
		sort($names);
		$connection_name = implode('_', $names);
		$defaults        = array(
			'name'        => $connection_name,
			'from'        => $names[0],
			'to'          => $names[1],
			'cardinality' => 'many-to-many',
			'reciprocal'  => true
		);
		$parameters      = wp_parse_args($parameters, $defaults);
		p2p_register_connection_type($parameters);
	}

	/**
	 * @static factory class fetches a post of the appropriate subclass
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

	}

	/**
	 * @static
	 * @param array $args - accepts a wp_query $args array which overwrites the defaults
	 * @return \WP_Query
	 */
	public static function fetchAllQuery($args = array())
	{
		$defaults = array('post_type'      => static::postName(),
						  'posts_per_page' => -1);
		$args     = wp_parse_args($args, $defaults);
		$query    = new WP_Query($args);

		if ($query->query_vars['error']) {
			die('Query error ' . $query->query_vars['error']);
		}

		foreach ($query->get_posts() as $i => $post) { //get_posts to apply filters
			$query->posts[$i] = static::fetch($post);
		}

		return $query;
	}

	/**
	 * @static Returns just the ooPosts from fetchAllQuery as an array
	 * @param array $args
	 * @return array
	 */
	static function fetchAll($args = array())
	{
		$query = static::fetchAllQuery($args);
		$iterable = new ooWP_Query($query);
		return $iterable;
	}

#endregion


}


?>
