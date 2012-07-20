<?php
class ooWP_Query extends WP_Query implements IteratorAggregate
{
	public function getIterator() {
		return new ArrayIterator($this->posts);
	}
}
