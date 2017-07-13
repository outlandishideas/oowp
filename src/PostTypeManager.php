<?php

namespace Outlandish\Wordpress\Oowp;

use Outlandish\Wordpress\Oowp\PostTypes\OowpPage;
use Outlandish\Wordpress\Oowp\PostTypes\OowpPost;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;
use Outlandish\Wordpress\Oowp\Util\AdminUtils;

class PostTypeManager
{
	protected $registered = false;
	protected $postTypes = [];
	protected $connections = [];

    private function __construct()
    {
    }

    protected static $instance;

    public static function get()
    {
        if (!self::$instance) {
            self::$instance = new PostTypeManager();
        }
        return self::$instance;
    }

	/**
	 * Registers a single post type from the given class
	 * @param string $className
	 * @throws \RuntimeException if $className is not a subclass of WordpressPost
	 */
    protected function registerPostType($className)
    {
		/** @var WordpressPost|string $className */

    	if (!is_subclass_of($className, 'Outlandish\Wordpress\Oowp\PostTypes\WordpressPost')) {
    		throw new \RuntimeException($className . ' is not a subclass of WordpressPost');
		}
    	$postType = $className::postType();
        if (in_array($postType, $this->postTypes)) {
            // already registered
            return;
        }
		if ($postType !== 'page' && $postType !== 'post' && $postType !== 'attachment' ) {
			$defaults = array(
				'labels' => AdminUtils::generateLabels($className::friendlyName(), $className::friendlyNamePlural()),
				'public' => true,
				'has_archive' => true,
				'rewrite' => array(
					'slug' => $postType,
					'with_front' => false
				),
				'show_ui' => true,
				'supports' => array(
					'title',
					'editor',
					'revisions',
				)
			);
			$registrationArgs = $className::getRegistrationArgs($defaults);
			register_post_type($postType, $registrationArgs);
		}

		$this->postTypes[$className] = $postType;
		$this->connections[$postType] = array();

		do_action('oowp/post_type_registered', $postType, $className);
    }

	/**
	 * Registers the given classes as OOWP post types
	 *
	 * Usage (php < 5.5):
	 * $manager->registerPostTypes(['namespace\of\posttypes\MyPostType', 'namespace\of\posttypes\AnotherPostType'])
	 * Usage (php >= 5.5):
	 * $manager->registerPostTypes([MyPostType::class, AnotherPostType::class])
	 *
	 * @param string[] $classNames
	 * @throws \Exception
	 */
    public function registerPostTypes($classNames)
    {
    	if ($this->registered) {
    		throw new \Exception('Can only register post types once');
		}

		/** @var WordpressPost[]|string[] $classNames */

		// add the built-in post/page classes after all of the others
		$classNames[] = 'Outlandish\Wordpress\Oowp\PostTypes\OowpPage';
		$classNames[] = 'Outlandish\Wordpress\Oowp\PostTypes\OowpPost';
		$classNames = array_unique($classNames);

		// register all classes
		foreach ($classNames as $className) {
            $this->registerPostType($className);
        }
        // then call onRegistrationComplete, so that connections can be set up
        foreach ($classNames as $className) {
			$className::onRegistrationComplete();
		}

		// add hook for posts to modify data before it is saved
		add_filter('save_post', function($postId, $postData) {
			if ($postData) {
				$postType = $postData->post_type;
				if (in_array($postType, $this->postTypes)) {
					$post = WordpressPost::fetchById($postId);
					if ($post) {
						$post->onSave($postData);
					}
				}
			}
		}, '99', 2); // use high priority value to ensure this happens after acf finishes saving its metadata

		$this->registered = true;
		do_action('oowp/all_post_types_registered', $this->postTypes);
    }

	/**
	 * Gets the name of the class that corresponds to the given post type
	 * @param $postType
	 * @return WordpressPost|string
	 */
    public function getClassName($postType)
    {
		$classNames = array_flip($this->postTypes);
		return array_key_exists($postType, $classNames) ? $classNames[$postType] : null;
    }

	/**
	 * Returns true if the post type was registered via registerPostTypes()
	 * @param string $postType
	 * @return bool
	 */
    public function postTypeIsRegistered($postType)
	{
		return in_array($postType, $this->postTypes);
	}

	/**
	 * Gets all of the post types registered via registerPostTypes()
	 * @return string[]
	 */
    public function getPostTypes()
	{
		return array_values($this->postTypes);
	}

	/**
	 * Registers a new connection between two post types
	 * @param string $postType
	 * @param string $targetPostType
	 * @param array $parameters
	 * @return bool|object
	 */
	public function registerConnection($postType, $targetPostType, $parameters)
	{
		if (!function_exists('p2p_register_connection_type')) {
			return null;
		}

		if(!array_key_exists($postType, $this->connections)) {
			$this->connections[$postType] = array();
		}
		if(!array_key_exists($targetPostType, $this->connections)) {
			$this->connections[$targetPostType] = array();
		}
		if(in_array($targetPostType, $this->connections[$postType]) || in_array($postType, $this->connections[$targetPostType])) {
			return false; //this connection has already been registered
		}
		$this->connections[$targetPostType][] = $postType;
		$this->connections[$postType][] = $targetPostType;

		$types = array($targetPostType, $postType);
		sort($types);

		$connection_name = $this->generateConnectionName($postType, $targetPostType);
		$defaults = array(
			'name' => $connection_name,
			'from' => $types[0],
			'to' => $types[1],
			'cardinality' => 'many-to-many',
			'reciprocal' => true
		);

		$parameters = wp_parse_args($parameters, $defaults);
		return p2p_register_connection_type($parameters);
	}

	/**
	 * Generates a connection name from the given types
	 * @param string $postType
	 * @param string $targetType
	 * @return string
	 */
	public function generateConnectionName($postType, $targetType)
	{
		// order them alphabetically, so that it doesn't matter which order they were supplied
		$types = array($postType, $targetType);
		sort($types);
		return implode('_', $types);
	}

	/**
	 * Gets all of the post types that the given post type is connected to
	 * @param string $postType
	 * @return string[]
	 */
	public function getConnections($postType)
	{
		return array_key_exists($postType, $this->connections) ? $this->connections[$postType] : array();
	}
}