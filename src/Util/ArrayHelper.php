<?php

namespace Outlandish\Wordpress\Oowp\Util;

class ArrayHelper {
    public $array = array();

    function __construct($array = array()) {
        $this->array = $array;
    }

    /**
     * Inserts the (key, value) pair into the array, before the given key. If the given key is not found,
     * it is inserted at the beginning
     *
     * @param string $beforeKey
     * @param string $key
     * @param mixed $value
     */
    function insertBefore($beforeKey, $key, $value) {
        $newArray = array();
        if (array_key_exists($beforeKey, $this->array)) {
            foreach ($this->array as $a=>$b) {
                if ($a == $beforeKey) {
                    $newArray[$key] = $value;
                }
                $newArray[$a] = $b;
            }
        } else {
            $newArray[$key] = $value;
            foreach ($this->array as $a=>$b) {
                $newArray[$a] = $b;
            }
        }
        $this->array = $newArray;
    }

    /**
     * Inserts the (key, value) pair into the array, after the given key. If the given key is not found,
     * it is inserted at the end
     *
     * @param string $afterKey
     * @param string $key
     * @param mixed $value
     */
    function insertAfter($afterKey, $key, $value) {
        if (array_key_exists($afterKey, $this->array)) {
            $newArray = array();
            foreach ($this->array as $a=>$b) {
                $newArray[$a] = $b;
                if ($a == $afterKey) {
                    $newArray[$key] = $value;
                }
            }
            $this->array = $newArray;
        } else {
            $this->array[$key] = $value;
        }
    }
}
