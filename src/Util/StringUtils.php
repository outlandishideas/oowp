<?php

namespace Outlandish\Wordpress\Oowp\Util;

class StringUtils
{
	/**
	 * Translates a camel case string into a string with underscores (e.g. firstName -> first_name)
	 * @param    string   $str    String in camel case format
	 * @return    string            $str Translated into underscore format
	 */
	static function fromCamelCase($str)
	{
		$str[0] = strtolower($str[0]);
  		return preg_replace_callback(
			'/([A-Z])/', function($c){	return "_" . strtolower($c[1]);	}, $str);

	}

	/**
	 * Translates a string with underscores into camel case (e.g. first_name -> firstName)
	 * @param    string   $str                     String in underscore format
	 * @param    bool     $capitalise_first_char   If true, capitalise the first char in $str
	 * @return   string                              $str translated into camel caps
	 */
	static function toCamelCase($str, $capitalise_first_char = false)
	{
		if ($capitalise_first_char) {
			$str[0] = strtoupper($str[0]);
		}
		return preg_replace_callback('/_([a-z])/', function ($c){return strtoupper($c[1]);}, $str);
	}


}
