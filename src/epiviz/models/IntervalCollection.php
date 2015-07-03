<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/2/2015
 * Time: 7:15 PM
 */

namespace epiviz\models;

/**
 * Interface IntervalCollection
 * @package epiviz\models
 */
interface IntervalCollection {
  /**
   * @param $i
   * @return Interval
   */
  public function get($i);

  /**
   * @return int
   */
  public function count();
}