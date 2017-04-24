<?php

namespace Outlandish\Wordpress\Oowp\Util;

class ReflectionUtils
{
	/**
	 * Returns the name of the function that called whatever called the caller :)
	 * e.g. if theFunction() called theOtherFunction(), theOtherFunction() could call getCaller(), which
	 * would return 'theFunction'
	 * @return string
	 */
	public static function getCaller() {
		return self::getCaller_internal(__FUNCTION__, 2);
	}

	public static function getCaller_internal($function, $diff) {

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


}