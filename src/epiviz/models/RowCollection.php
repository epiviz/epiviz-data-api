<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/29/2015
 * Time: 4:28 PM
 */

namespace epiviz\models;

class RowCollection implements IntervalCollection {
  /**
   * @var array
   */
  public $values;

  /**
   * @var int
   */
  public $globalStartIndex;

  /**
   * @var bool
   */
  public $useOffset;

  /**
   * @var array
   */
  private $start;

  /**
   * @var array
   */
  private $end;

  /**
   * @var array
   */
  private $index;

  /**
   * @var array
   */
  private $metadata;

  /**
   * @var array
   */
  private $metadataCols;

  /**
   * @var bool
   */
  private $storeIndex;

  /**
   * @var bool
   */
  private $storeEnd;

  /**
   * @var int
   */
  private $count = 0;

  /**
   * @param array $metadata_cols
   * @param bool $use_offset
   * @param bool $store_index
   * @param bool $store_end
   */
  public function __construct(array $metadata_cols = null, $use_offset = false, $store_index = true, $store_end = true) {
    $this->metadataCols = $metadata_cols;
    $this->useOffset = $use_offset;
    $this->storeIndex = $store_index;
    $this->storeEnd = $store_end;

    $this->start = array();
    $this->values = array('start' => &$this->start);

    if ($store_end) {
      $this->end = array();
      $this->values['end'] = &$this->end;
    }

    if ($store_index) {
      $this->index = array();
      $this->values['index'] = &$this->index;
    }

    if (!empty($metadata_cols)) {
      $this->metadata = array();
      foreach ($metadata_cols as $col) {
        $this->metadata[$col] = array();
      }
      $this->values['metadata'] = &$this->metadata;
    }
  }

  /**
   * @param array $row
   */
  public function addDbRecord(array $row) {
    $i = 0;
    $index = 0 + $row[$i];
    if ($this->count == 0) {
      $this->globalStartIndex = $index;
    }
    if ($this->storeIndex) {
      $this->index[] = $index;
    }
    ++$i;

    $this->start[] = 0 + $row[$i++];
    if ($this->storeEnd) {
      $this->end[] = $row[$i++];
    }

    if (!empty($this->metadataCols)) {
      $n = count($this->metadataCols);
      for ($j = 0; $j < $n; ++$j) {
        $this->metadata[$this->metadataCols[$j]][] = $row[$i++];
      }
    }

    ++$this->count;
  }

  /**
   * @param int $index
   * @param int $start
   * @param int $end
   * @param array $metadata
   */
  public function add($index, $start, $end=null, array $metadata=null) {
    if ($this->count == 0) {
      $this->globalStartIndex = $index;
    }
    if ($this->storeIndex) {
      $this->index[] = $index;
    }

    $this->start[] = $start;
    if ($this->storeEnd) {
      $this->end[] = $end;
    }

    if (!empty($this->metadataCols)) {
      foreach ($this->metadataCols as $col) {
        $this->metadata[$col][] = idx($metadata, $col);
      }
    }
  }

  /**
   * @return int
   */
  public function count() { return $this->count; }

  /**
   * @return bool
   */
  public function storeIndex() { return $this->storeIndex; }

  /**
   * @return bool
   */
  public function storeEnd() { return $this->storeEnd; }

  /**
   * @param int $i
   * @return RowCollection\Row
   */
  public function get($i) {
    return new RowCollection\Row($this, $i);
  }

  /**
   * @param array $order
   * @return RowCollection
   */
  public function reorder(array &$order) {
    if ($this->count == 0) { return $this; }

    $global_start_index = $this->globalStartIndex;
    $store_index = $this->storeIndex;
    $store_end = $this->storeEnd;
    $count = $this->count;
    $use_offset = $this->useOffset;

    $index = null;
    $start = null;
    $end = null;
    $metadata = null;

    if ($store_index) {
      $index = range($global_start_index, $global_start_index + $count - 1);
    }

    $last_end = $this->start[0];
    $start = array();
    if ($store_end) {
      $end = array();
    }
    foreach ($order as $i) {
      $start[] = $last_end;
      if ($store_end) {
        $last_end = $this->end[$i] - $this->start[$i] + $last_end;
        $end[] = $last_end;
      } else {
        $last_end = 1 + $last_end;
      }
    }

    if (!empty($this->metadataCols)) {
      $metadata = array();
      foreach ($this->metadataCols as $col) {
        $metadata[$col] = array();
        $metadata_col = &$this->metadata[$col];
        foreach ($order as $i) {
          $metadata[$col][] = $metadata_col[$i];
        }
      }
    }

    $ret = new RowCollection($this->metadataCols, $use_offset, $store_index, $store_end);
    $ret->globalStartIndex = $global_start_index;
    $ret->count = $count;
    $ret->index = &$index;
    $ret->start = &$start;
    $ret->end = &$end;
    $ret->metadata = &$metadata;

    $ret->values = array('start' => &$start);

    if ($store_end) {
      $ret->values['end'] = &$end;
    }

    if ($store_index) {
      $ret->values['index'] = &$index;
    }

    if (!empty($this->metadataCols)) {
      $ret->values['metadata'] = &$metadata;
    }

    return $ret;
  }
}

namespace epiviz\models\RowCollection;

use epiviz\models\Interval;

/**
 * Class Row
 * @package epiviz\models\RowCollection
 */
class Row implements Interval {
  /**
   * @var \epiviz\models\RowCollection
   */
  private $rowCollection;

  /**
   * @var int
   */
  private $i;

  /**
   * @param \epiviz\models\RowCollection $row_collection
   * @param int $i
   */
  public function __construct($row_collection, $i) {
    $this->rowCollection = $row_collection;
    $this->i = $i;
  }

  /**
   * @return int
   */
  public function index() {
    return $this->rowCollection->storeIndex() ?
      $this->rowCollection->values['index'][$this->i] :
      $this->rowCollection->globalStartIndex + $this->i;
  }

  /**
   * @return int
   */
  public function start() { return $this->rowCollection->values['start'][$this->i]; }

  /**
   * @return int
   */
  public function end() {
    return $this->rowCollection->storeEnd() ?
      $this->rowCollection->values['end'][$this->i] :
      $this->rowCollection->values['start'][$this->i] + 1;
  }

  /**
   * @param string $col
   * @return string|int
   */
  public function metadata($col) { return $this->rowCollection->values['metadata'][$col][$this->i]; }
}
