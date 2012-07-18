<?php
class ooWP_Query extends WP_Query implements Iterator
{

	public function __construct($wp_query)
	{
		foreach((array)$wp_query as $key => $var){
			$this->$key = $var;
		}
	}

	public function rewind()
	{
		reset($this->posts);
	}

	public function current()
	{
		return current($this->posts);
	}

	public function key()
	{
		return key($this->posts);
	}

	public function next()
	{
		return next($this->posts);

	}

	public function valid()
	{
		$key = key($this->posts);
		$var = ($key !== NULL && $key !== FALSE);
		return $var;
	}

	/**
	 * Stores $this as the global $wp_query, executes the passed-in WP function, then reverts $wp_query
	 * @return mixed
	 */
	protected function callGlobalQuery() {
		$args     = func_get_args();
		$function = array_shift($args);
		global $wp_query;
		$oldQuery = $wp_query;
		$wp_query = $this;
		$returnVal = call_user_func_array($function, $args);
		$wp_query = $oldQuery;
		return $returnVal;
	}

	/**
	 * Prints the prev/next links for this query
	 * @param string $sep
	 * @param string $preLabel
	 * @param string $nextLabel
	 */
	public function postsNavLink($sep = '', $preLabel = '', $nextLabel = '') {
		$this->callGlobalQuery('posts_nav_link', $sep, $preLabel, $nextLabel);
	}
}
