<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/29/2015
 * Time: 4:28 PM
 */

namespace epiviz\models;

class RowCollection {
  public $values;
  public $globalStartIndex;
  public $useOffset;

  private $start;
  private $end;
  private $index;
  private $metadata;
  private $metadataCols;

  private $storeIndex;
  private $storeEnd;
  private $count = 0;

  public function __construct($metadata_cols = null, $use_offset = false, $store_index = true, $store_end = true) {
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

  public function addRow($row) {
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

  public function count() { return $this->count; }
  public function storeIndex() { return $this->storeIndex; }
  public function storeEnd() { return $this->storeEnd; }

  public function get($i) {
    return new RowCollection\Row($this, $i);
  }
}

namespace epiviz\models\RowCollection;

class Row {
  private $rowCollection;
  private $i;

  /**
   * @param \epiviz\models\RowCollection $row_collection
   * @param int $i
   */
  public function __construct($row_collection, $i) {
    $this->rowCollection = $row_collection;
    $this->i = $i;
  }

  public function index() {
    return $this->rowCollection->storeIndex() ?
      $this->rowCollection->values['index'][$this->i] :
      $this->rowCollection->globalStartIndex + $this->i;
  }

  public function start() { return $this->rowCollection->values['start'][$this->i]; }

  public function end() {
    return $this->rowCollection->storeEnd() ?
      $this->rowCollection->values['end'][$this->i] :
      $this->rowCollection->values['start'][$this->i] + 1;
  }

  public function metadata($col) { return $this->rowCollection->values['metadata'][$col][$this->i]; }
}
