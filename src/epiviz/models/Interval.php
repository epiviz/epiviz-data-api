<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/2/2015
 * Time: 7:17 PM
 */

namespace epiviz\models;

/**
 * Interface Interval
 * @package epiviz\models
 */
interface Interval {
  /**
   * @return int
   */
  public function start();

  /**
   * @return int
   */
  public function end();
}