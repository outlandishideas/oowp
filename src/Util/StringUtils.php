<?php

namespace Outlandish\Wordpress\Oowp\Util;

class StringUtils
{
    /**
     * Translates a camel case string into a string with underscores (e.g. firstName -> first_name)
     * @param string $str String in camel case format
     * @return string $str Translated into underscore format
     */
    public static function fromCamelCase(string $str) : string
    {
        $str[0] = strtolower($str[0]);
        return preg_replace_callback(
            '/([A-Z])/',
            function ($c) {
                return "_" . strtolower($c[1]);
            },
            $str
        );
    }

    /**
     * Translates a string with underscores into camel case (e.g. first_name -> firstName)
     * @param string $str String in underscore format
     * @param bool $capitaliseFirstChar If true, capitalise the first char in $str
     * @return string $str translated into camel caps
     */
    public static function toCamelCase(string $str, bool $capitaliseFirstChar = false) : string
    {
        if ($capitaliseFirstChar) {
            $str[0] = strtoupper($str[0]);
        }
        return preg_replace_callback(
            '/_([a-z])/',
            function ($c) {
                return strtoupper($c[1]);
            },
            $str
        );
    }

    /**
     * Turns an array of key=>value attributes into html string
     * @static
     *
     * @param array $attrs key=>value attributes
     * @return string HTML for including in an element
     */
    public static function makeAttributeString(array $attrs) : string
    {
        $attributeStrings = [];
        foreach ($attrs as $key => $value) {
            $value = esc_attr($value);
            $attributeStrings[] = $key . '="' . $value . '"';
        }
        return implode(' ', $attributeStrings);
    }

}
