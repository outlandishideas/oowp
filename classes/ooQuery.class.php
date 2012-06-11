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

}
