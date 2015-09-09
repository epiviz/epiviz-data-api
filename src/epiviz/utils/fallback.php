<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 9/9/2015
 * Time: 9:25 AM
 */

/**
 * TODO: After upgrading to PHP 5.5, switch to the internal boolval
 * @param string|bool $var
 * @return bool
 */
function boolval($var) {
  if (is_bool($var)) { return $var; }
  switch ($var) {
    case 'true': return true;
    case 'false': return false;
    default: throw new InvalidArgumentException('Cannot parse value '.$var);
  }
}
