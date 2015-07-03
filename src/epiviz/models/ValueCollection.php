<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/2/2015
 * Time: 10:30 PM
 */

namespace epiviz\models;


class ValueCollection {
  /**
   * @var int
   */
  public $globalStartIndex;

  /**
   * @var array
   */
  public $values;

  public function __construct() {
    $this->values = array();
  }

  /**
   * @param int $value
   * @param int $index
   */
  public function add($value, $index) {
    if ($this->globalStartIndex === null) { $this->globalStartIndex = $index; }
    $this->values[] = $value;
  }
}