<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/2/2015
 * Time: 10:30 PM
 */

namespace epiviz\models;

use epiviz\models\ValueCollection\ValueInterval;

class ValueCollection implements IntervalCollection {
  /**
   * @var int
   */
  public $globalStartIndex;

  /**
   * @var array
   */
  public $values = array();

  /**
   * @var array
   */
  private $start = array();

  /**
   * @var array
   */
  private $end = array();

  /**
   * @var int
   */
  private $count = 0;

  public function __construct() {}

  /**
   * @param float $value
   * @param int $index
   * @param int $start
   * @param int $end
   */
  public function add($value, $index, $start, $end) {
    if ($this->globalStartIndex === null) { $this->globalStartIndex = $index; }
    $this->values[] = $value;
    $this->start[] = $start;
    $this->end[] = $end;
    ++$this->count;
  }

  /**
   * @return int
   */
  public function count() { return $this->count; }

  /**
   * @param int $i
   * @return int
   */
  public function start($i) { return $this->start[$i]; }

  /**
   * @param int $i
   * @return int
   */
  public function end($i) { return $this->end[$i]; }

  /**
   * @param int $i
   * @return ValueInterval
   */
  public function get($i) { return new ValueInterval($this, $i); }

  /**
   * @param array $order
   * @return ValueCollection
   */
  public function reorder(array &$order) {
    if ($this->count == 0) { return $this; }

    $global_start_index = $this->globalStartIndex;
    $count = $this->count;

    $start = array();
    $end = array();
    $values = array();

    $last_end = $this->start[0];
    foreach ($order as $i) {
      $start[] = $last_end;
      $last_end = $this->end[$i] - $this->start[$i] + $last_end;
      $end[] = $last_end;
      $values[] = $this->values[$i];
    }

    $ret = new ValueCollection();
    $ret->globalStartIndex = $global_start_index;
    $ret->count = $count;
    $ret->start = &$start;
    $ret->end = &$end;
    $ret->values = &$values;

    return $ret;
  }
}

namespace epiviz\models\ValueCollection;

use epiviz\models\ValueCollection;
use epiviz\models\Interval;

/**
 * Class ValueInterval
 * @package epiviz\models\ValueCollection
 */
class ValueInterval implements Interval {
  /**
   * @var ValueCollection
   */
  private $valueCollection;

  /**
   * @var int
   */
  private $i;

  /**
   * @param ValueCollection $value_collection
   * @param int $i
   */
  public function __construct(ValueCollection $value_collection, $i) {
    $this->valueCollection = $value_collection;
    $this->i = $i;
  }

  /**
   * @return int
   */
  public function start() { return $this->valueCollection->start($this->i); }

  /**
   * @return int
   */
  public function end() { return $this->valueCollection->end($this->i); }

  /**
   * @return float
   */
  public function value() { return $this->valueCollection->values[$this->i]; }
}