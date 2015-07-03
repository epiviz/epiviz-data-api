<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/3/2015
 * Time: 1:09 PM
 */

namespace epiviz\api;

/**
 * Class ValueAggregatorFactory
 * @package epiviz\api
 */
class ValueAggregatorFactory {
  /**
   * @var array
   */
  private $aggregators = array();

  /**
   * @param ValueAggregator $aggregator
   */
  public function register(ValueAggregator $aggregator) {
    $this->aggregators[$aggregator->id()] = $aggregator;
  }

  /**
   * @param string $aggregator_id
   * @return ValueAggregator
   */
  public function get($aggregator_id) { return $this->aggregators[$aggregator_id]; }

  /**
   * @return array
   */
  public function values() { return $this->aggregators; }
}
