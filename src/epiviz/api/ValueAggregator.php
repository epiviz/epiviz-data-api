<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/3/2015
 * Time: 1:08 PM
 */

namespace epiviz\api;

/**
 * Interface ValueAggregator
 * @package epiviz\api
 */
interface ValueAggregator {
  /**
   * @param array $values
   * @return float
   */
  public function aggregate(array &$values);

  /**
   * @return string
   */
  public function id();
}