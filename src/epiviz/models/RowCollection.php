<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/29/2015
 * Time: 4:28 PM
 */

namespace epiviz\models;

use epiviz\models\RowCollection\Row;

/**
 * Class RowCollection
 * @package epiviz\models
 */
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
   * @var array
   */
  private $levels;

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
   * @param array $levels
   * @param bool $use_offset
   * @param bool $store_index
   * @param bool $store_end
   */
  public function __construct(array $metadata_cols = null, array $levels = null, $use_offset = false, $store_index = true, $store_end = true) {
    $this->globalStartIndex = null;
    $this->metadataCols = $metadata_cols;
    $this->levels = $levels;
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

    if (empty($metadata_cols)) { $metadata_cols = array(); }
    if (empty($levels)) { $levels = array(); }

    $metadata_cols = array_merge($metadata_cols, $levels);
    if (!empty($levels)) { $metadata_cols[] = 'lineage'; }
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

    if (!empty($this->levels)) {
      $lineage_label = $row[$i++];
      $lineage = $row[$i++];
      $depth = $row[$i++];

      $lineage_labels = explode(',', $lineage_label);
      foreach ($this->levels as $level => $label) {
        $this->metadata[$label][] = idx($lineage_labels, $level, null);
      }
      $this->metadata['lineage'][] = $lineage;
    }

    ++$this->count;
  }

  /**
   * @param int $index
   * @param int $start
   * @param int $end
   * @param array $metadata
   * @param array $lineage_labels
   * @param string $lineage
   */
  public function add($index, $start, $end=null, array $metadata=null, array $lineage_labels=null, $lineage=null) {
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

    if (!empty($this->levels) && !empty($lineage_labels)) {
      foreach ($this->levels as $level => $label) {
        $this->metadata[$label][] = idx($lineage_labels, $level, null);
      }

      if ($lineage !== null) {
        $this->metadata['lineage'][] = $lineage;
      }
    }

    ++$this->count;
  }

  /**
   * @param Row $row
   */
  public function addRow(Row $row) {
    if ($this->count == 0) {
      $this->globalStartIndex = $row->index();
    }
    if ($this->storeIndex) {
      $this->index[] = $row->index();
    }

    $this->start[] = $row->start();
    if ($this->storeEnd) {
      $this->end[] = $row->end();
    }

    $metadata = $row->parentCollection()->metadata;
    $i = $row->i();
    if (!empty($this->metadataCols)) {
      foreach ($this->metadataCols as $col) {
        $col_metadata = idx($metadata, $col);
        $this->metadata[$col][] = $col_metadata !== null ? $col_metadata[$i] : null;
      }
    }

    if (!empty($this->levels)) {
      foreach ($this->levels as $level => $label) {
        $col_metadata = idx($metadata, $label);
        $this->metadata[$label][] = $col_metadata !== null ? $col_metadata[$i] : null;
      }

      $col_metadata = idx($metadata, 'lineage');
      $this->metadata['lineage'][] = $col_metadata !== null ? $col_metadata[$i] : null;
    }

    ++$this->count;
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
   * @return bool
   */
  public function useOffset() { return $this->useOffset; }

  /**
   * @return array
   */
  public function metadataCols() { return $this->metadataCols; }

  /**
   * @return array
   */
  public function levels() { return $this->levels; }

  /**
   * @param int $i
   * @return RowCollection\Row
   */
  public function get($i) {
    return new RowCollection\Row($this, $i);
  }

  /**
   * @return RowCollection\Row|null
   */
  public function first() {
    if ($this->count == 0) { return null; }
    return $this->get(0);
  }

  /**
   * @return RowCollection\Row|null
   */
  public function last() {
    if ($this->count == 0) { return null; }
    return $this->get($this->count - 1);
  }

  public function firstOverlap($start, $end) {
    $low = 0;
    $high = $this->count - 1;

    $i = null;
    while ($low <= $high) {
      $mid = ($low + $high) >> 1;
      if ($end <= $this->start[$mid]) {
        $high = $mid - 1;
      } else if ($start >= $this->end[$mid]) {
        $low = $mid + 1;
      } else {
        $i = $mid;
        $high = $mid - 1;
      }
    }

    return $i !== null ? $this->get($i) : null;
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

    $metadata_cols = array_keys($this->metadata);
    if (!empty($metadata_cols)) {
      $metadata = array();
      foreach ($metadata_cols as $col) {
        $metadata[$col] = array();
        $metadata_col = &$this->metadata[$col];
        foreach ($order as $i) {
          $metadata[$col][] = $metadata_col[$i];
        }
      }
    }

    $ret = new RowCollection($this->metadataCols, $this->levels, $use_offset, $store_index, $store_end);
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

    if (!empty($metadata_cols)) {
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

  private $metadataMap;

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

  /**
   * @return array
   */
  public function metadataMap() {
    if ($this->metadataMap == null) {
      $map = array();
      foreach ($this->rowCollection->values['metadata'] as $col => $metadata_arr) {
        $map[$col] = $metadata_arr[$this->i];
      }
      $this->metadataMap = $map;
    }
    return $this->metadataMap;
  }

  /**
   * @return \epiviz\models\RowCollection
   */
  public function parentCollection() {
    return $this->rowCollection;
  }

  /**
   * @return int
   */
  public function i() { return $this->i; }
}
