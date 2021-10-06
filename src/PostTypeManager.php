<?php

namespace Outlandish\Wordpress\Oowp;

use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class PostTypeManager
{
    protected $registered = false;
    protected $postTypes = [];
    protected $connections = [];

    /**
     * This base PostTypeManager is expected to be used with a singleton pattern, so directly instantiating it
     * is likely to be a mistake. The constructor is therefore `protected`. But if you're really sure you want to
     * use a different pattern when extending it, you can do so and make your subclass's constructor public.
     *
     * @see PostTypeManager::get()
     */
    protected function __construct()
    {
    }

    protected static $instance;

    /**
     * Singleton getter that now allows for subclasses without a copy of the method (via `static::`).
     *
     * @return PostTypeManager  This class or a subclass.
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Registers a single post type from the given class
     *
     * @param string|WordpressPost $className
     * @throws \RuntimeException if $className is not a subclass of WordpressPost
     */
    protected function registerPostType($className)
    {
        if (!is_subclass_of($className, 'Outlandish\Wordpress\Oowp\PostTypes\WordpressPost')) {
            throw new \RuntimeException($className . ' is not a subclass of WordpressPost');
        }

        $postType = $className::postType();
        if (in_array($postType, $this->postTypes)) {
            // already registered
            return;
        }
        if ($className::canBeRegistered()) {
            $registrationArgs = $className::getRegistrationArgs();
            register_post_type($postType, $registrationArgs);
        }

        $this->postTypes[$className] = $postType;

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
        $classNames   = array_unique($classNames);

        // register all classes
        foreach ($classNames as $className) {
            $this->registerPostType($className);
        }
        // then call onRegistrationComplete, so that connections can be set up
        foreach ($classNames as $className) {
            $className::onRegistrationComplete();
        }

        // add hook for posts to deal with data after it is saved
        add_filter('save_post', function ($postId, $postData) {
            if ($postData) {
                $postType = $postData->post_type;
                if (in_array($postType, $this->postTypes)) {
                    $post = WordpressPost::fetchOne([
                        'p' => $postId,
                        'post_type' => $postType,
                        'post_status' => $postData->post_status
                    ]);
                    if ($post) {
                        $post->onSave($postData);
                    }
                }
            }
        }, 99, 2); // use high priority value to ensure this happens after acf finishes saving its metadata

        $this->registered = true;
        do_action('oowp/all_post_types_registered', $this->postTypes);
    }

    /**
     * Gets the name of the class that corresponds to the given post type
     *
     * @param string $postType
     * @return WordpressPost|string
     */
    public function getClassName($postType)
    {
        $classNames = array_flip($this->postTypes);
        return array_key_exists($postType, $classNames) ? $classNames[$postType] : null;
    }

    /**
     * Returns true if the given fully-qualified class name represents a post type
     * registered via registerPostTypes().
     * @param string $className Fully-qualified name of a class which might be a post type.
     * @return bool
     */
    public function postClassIsRegistered($className)
    {
        if (!class_exists($className)) {
            return false;
        }

        return array_key_exists($className, $this->postTypes);
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
     * @param string $connectionName
     * @return bool|object|null
     */
    public function registerConnection($postType, $targetPostType, $parameters, $connectionName = null)
    {
        if (!function_exists('p2p_register_connection_type')) {
            return null;
        }

        if (!$connectionName) {
            $connectionName = $this->generateConnectionName($postType, $targetPostType);
        }

        if (array_key_exists($connectionName, $this->connections)) {
            return $this->connections[$connectionName]->connection;
        }

        $defaults = array(
            'name' => $connectionName,
            'from' => $postType,
            'to' => $targetPostType,
            'cardinality' => 'many-to-many',
            'reciprocal' => true
        );

        $parameters = wp_parse_args($parameters, $defaults);
        $connection = p2p_register_connection_type($parameters);

        $this->connections[$connectionName] = (object)[
            'parameters' => $parameters,
            'connection' => $connection
        ];

        return $connection;
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
    public function getConnectedPostTypes($postType)
    {
        $types = [];
        foreach ($this->connections as $connection) {
            if ($connection->parameters['from'] === $postType) {
                $types[] = $connection->parameters['to'];
            }
            if ($connection->parameters['to'] === $postType) {
                $types[] = $connection->parameters['from'];
            }
        }
        return array_unique($types);
    }

    /**
     * Gets all of the connection names that go between (one of) $postTypeA and (one of) $postTypeB
     * @param string|string[] $postTypeA
     * @param string|string[] $postTypeB
     * @return string[]
     */
    public function getConnectionNames($postTypeA, $postTypeB)
    {
        $connectionNames = [];
        if ($postTypeA && $postTypeB) {
            if (!is_array($postTypeB)) {
                $postTypeB = [$postTypeB];
            }
            if (!is_array($postTypeA)) {
                $postTypeA = [$postTypeA];
            }
            foreach ($this->connections as $name => $connection) {
                if (in_array($connection->parameters['from'], $postTypeA) && in_array($connection->parameters['to'],
                        $postTypeB)) {
                    $connectionNames[] = $name;
                }
                if (in_array($connection->parameters['from'], $postTypeB) && in_array($connection->parameters['to'],
                        $postTypeA)) {
                    $connectionNames[] = $name;
                }
            }
        }
        return array_unique($connectionNames);
    }
}
