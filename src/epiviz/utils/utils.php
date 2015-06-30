<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/17/2015
 * Time: 11:31 AM
 */

/**
 * @param array $arr
 * @param string|int $key
 * @param $default
 * @return *
 */
function idx(array &$arr=null, $key, $default = null) {
  return ($arr === null || !is_array($arr) || !array_key_exists($key, $arr)) ? $default : $arr[$key];
}

/**
 * @param array $array
 * @return bool
 */
function is_assoc(array &$array=null) {
  if (empty($array)) { return false; }
  return (bool)count(array_filter(array_keys($array), 'is_string'));
}

/**
 * Searches the specified array of ints for the specified value using the
 * binary search algorithm.  The array must be sorted (as
 * by the {@link #sort(int[])} method) prior to making this call.  If it
 * is not sorted, the results are undefined.  If the array contains
 * multiple elements with the specified value, there is no guarantee which
 * one will be found.
 *


/**
 * Searches the specified array of ints for the specified value using the
 * binary search algorithm.  The array must be sorted prior to making this call.  If it
 * is not sorted, the results are undefined.  If the array contains
 * multiple elements with the specified value, there is no guarantee which
 * one will be found.
 *
 * @param * $needle the value to be searched for
 * @param array $haystack the array to be searched
 * @param callable $cmp
 * @param int|null $from_index
 * @param int|null $to_index
 * @return int index of the search key, if it is contained in the array;
 *         otherwise, <tt>(-(<i>insertion point</i>) - 1)</tt>.  The
 *         <i>insertion point</i> is defined as the point at which the
 *         key would be inserted into the array: the index of the first
 *         element greater than the key, or <tt>a.length</tt> if all
 *         elements in the array are less than the specified key.  Note
 *         that this guarantees that the return value will be &gt;= 0 if
 *         and only if the key is found.
 */
function binary_search($needle, array $haystack, callable $cmp = null, $from_index = null, $to_index = null) {
  if ($cmp === null) {
    $cmp = function($v1, $v2) {
      if ($v1 < $v2) { return -1; }
      if ($v1 > $v2) { return 1; }
      return 0;
    };
  }

  if ($from_index === null) { $from_index = 0; }
  if ($to_index === null) { $to_index = count($haystack); }

  $low = $from_index;
  $high = $to_index - 1;

  while ($low <= $high) {
    $mid = ($low + $high) >> 1;
    $test = $cmp($needle, $haystack[$mid]);
    if ($test > 0) {
      $low = $mid + 1;
    } else if ($test < 0) {
      $high = $mid - 1;
    } else {
      return $mid; // key found
    }
  }

  return -($low + 1);  // key not found.
}

/**
 * @param $val
 * @param $min
 * @param $max
 * @return bool
 */
function between($val, $min = null, $max = null) {
  return ($min === null && $max === null) ||
    ($min === null && $val < $max) ||
    ($max === null && $min <= $val) ||
    ($min <= $val && $val < $max);
}
