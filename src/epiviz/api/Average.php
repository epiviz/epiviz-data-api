<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/3/2015
 * Time: 1:13 PM
 */

namespace epiviz\api;


class Average implements ValueAggregator {
  /**
   * @return string
   */
  public function id() { return 'average'; }

  /**
   * @param array $values
   * @return float
   */
  public function aggregate(array &$values) {
    if (empty($values)) { return 0; }
    return array_sum($values) / count($values);
  }
}