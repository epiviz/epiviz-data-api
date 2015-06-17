<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/17/2015
 * Time: 11:31 AM
 */


function idx($arr, $key, $default = null) {
  if (array_key_exists($key, $arr)) {
    return $arr[$key];
  }
  return $default;
}

function is_assoc($array) {
  return (bool)count(array_filter(array_keys($array), 'is_string'));
}
