<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/2/2015
 * Time: 7:51 PM
 */

namespace epiviz\models;


class SimpleIntervalCollection implements IntervalCollection {
  private $intervals;

  public function __construct(array &$intervals) {
    $this->intervals = $intervals;
  }

  public function get($i) { return $this->intervals[$i]; }

  public function count() { return count($this->intervals); }
}