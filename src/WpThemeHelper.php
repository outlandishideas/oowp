<?php

namespace Outlandish\Wordpress\Oowp;

/**
 * Helper class for common theme-related functionality.
 *
 * Subclass this in your theme's classes directory, and put all theme-specific functionality in its init() function
 * instead of in functions.php, then call init() inside a wordpress 'init' action listener
 */
class WpThemeHelper
{
    private static WpThemeHelper $instance;

    protected function __construct()
    {
    }

    /**
     * @static
     *
     * @return self Singleton instance
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function init()
    {
    }

    public function siteInfo($info)
    {
        return get_bloginfo($info);
    }

    /**
     * Gets the url for an asset in this theme.
     * With no argument, this is just the root directory of this theme
     *
     * @param string $relativePath
     * @return string
     */
    public function assetUrl($relativePath = '')
    {
        $relativePath = '/' . ltrim($relativePath, '/');
        return get_template_directory_uri() . $relativePath;
    }

    /**
     * @return string
     * @deprecated Use assetUrl() instead
     *
     */
    public function siteThemeURL()
    {
        return $this->assetUrl();
    }

    public function directory($path = '')
    {
        return get_stylesheet_directory() . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function siteTitle()
    {
        return $this->siteInfo('name');
    }

    /**
     * @return \wpdb
     */
    public function db() : \wpdb
    {
        global $wpdb;
        return $wpdb;
    }
}
