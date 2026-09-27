<?php

namespace Outlandish\Wordpress\Oowp;

use WP_Query;

class QueryVars
{
    private $args;

    function __construct($data)
    {
        $this->args = $data instanceof WP_Query ? $data->query_vars : $data
    }

    public function hasArg($arg)
    {
        return isset($this->args[$arg]);
    }

    public function arg($arg)
    {
        return $this->args[$arg];
    }

    public function isForPostType($postType)
    {
        return in_array(
            $postType,
            is_array($this->args['post_type']) ? $this->args['post_type'] : ['any', $this->args['post_type']]
        );
    }
}
