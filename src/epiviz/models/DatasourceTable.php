<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/4/2015
 * Time: 1:03 PM
 */

namespace epiviz\models;
use epiviz\api\ValueAggregator;
use epiviz\api\ValueAggregatorFactory;

/**
 * TODO: Use in a future getCombined method
 * Class DatasourceTable
 * @package epiviz\models
 */
class DatasourceTable {
  /**
   * @var RowCollection
   */
  public $rows;

  /**
   * @var array
   */
  public $cols;

  /**
   * @var int
   */
  public $globalStartIndex;

  private $rowCollection;
  private $valueCollections = array();

  private $measurements;
  private $metadataCols;
  private $levels;
  private $useOffset;
  private $storeIndex;
  private $storeEnd;

  /**
   * @param array $measurements
   * @param array|null $metadata_cols
   * @param array $levels
   * @param bool $use_offset
   * @param bool $store_index
   * @param bool $store_end
   * @return DatasourceTable
   */
  public static function createEmpty(array $measurements, array $metadata_cols = null, array $levels, $use_offset = false, $store_index = true, $store_end = true) {
    $ret = new DatasourceTable();
    $ret->measurements = $measurements;
    $ret->metadataCols = $metadata_cols;
    $ret->levels = $levels;
    $ret->useOffset = $use_offset;
    $ret->storeIndex = $store_index;
    $ret->storeEnd = $store_end;

    $ret->rowCollection = new RowCollection($metadata_cols, $levels, $use_offset, $store_index, $store_end);
    $ret->rows = &$ret->rowCollection->values;
    $ret->globalStartIndex = &$ret->rowCollection->globalStartIndex;

    $value_collections = array_flip($measurements);
    array_walk($value_collections, function(&$v, $m) { $v = new ValueCollection(); });
    $ret->valueCollections = $value_collections;

    $cols = array();
    array_walk($value_collections, function(ValueCollection &$val_col, $m) use (&$cols) {
      $cols[$m] = &$val_col->values;
    });
    $ret->cols = &$cols;

    return $ret;
  }

  /**
   * @param RowCollection $rows
   * @param array $values
   * @return DatasourceTable
   */
  public static function createFromData(RowCollection $rows, array $values) {
    $ret = new DatasourceTable();
    $ret->measurements = array_keys($values);
    $ret->metadataCols = $rows->metadataCols();
    $ret->levels = $rows->levels();
    $ret->useOffset = $rows->useOffset();
    $ret->storeIndex = $rows->storeIndex();
    $ret->storeEnd = $rows->storeEnd();

    $ret->rowCollection = $rows;
    $ret->rows = &$ret->rowCollection->values;
    $ret->globalStartIndex = &$ret->rowCollection->globalStartIndex;

    $ret->valueCollections = $values;

    $cols = array();
    array_walk($values, function(ValueCollection &$val_col, $m) use (&$cols) {
      $cols[$m] = &$val_col->values;
    });
    $ret->cols = &$cols;

    return $ret;
  }

  public function __construct() {

  }

  /**
   *     0,   1,     2,            3,       4,     5,   6,   7,           8,   9-...
   * $r: row, start, lineageLabel, lineage, depth, val, col, measurement, end, ...metadata
   * @param array $r
   */
  public function addDbRecord(array $r) {
    $last_row = $this->rowCollection->last();
    if ($last_row === null || $last_row->index() < $r[0]) {
      $this->rowCollection->add($r[0], $r[1], $r[8],
        array_slice($r, 9), explode(',', $r[2]), $r[3]);
    }

    $values = &$this->valueCollections[$r[7]];
    for ($i = $values->count(); $i < $this->rowCollection->count() - 1; ++$i) {
      $row = $this->rowCollection->get($i);
      $values->add(0, $row->index(), $row->start(), $row->end());
    }
    $values->add($r[5], $r[0], $r[1], $r[8]);
  }

  /**
   * @param int $index
   * @param int $start
   * @param int $end
   * @param float $value
   * @param string $measurement
   * @param array $metadata
   * @param string|null $lineage_label
   * @param string|null $lineage
   */
  public function addEntry($index, $start, $end, $value, $measurement, array $metadata, $lineage_label=null, $lineage=null) {
    $last_row = $this->rowCollection->last();
    if ($last_row === null || $last_row->index() < $index) {
      $this->rowCollection->add($index, $start, $end,
        $metadata, $lineage_label !== null ? explode(',', $lineage_label) : null, $lineage);
    }

    $values = &$this->valueCollections[$measurement];
    for ($i = $values->count(); $i < $this->rowCollection->count() - 1; ++$i) {
      $row = $this->rowCollection->get($i);
      $values->add(0, $row->index(), $row->start(), $row->end());
    }
    $values->add($value, $index, $start, $end);
  }

  /**
   * @param RowCollection\Row $row
   * @param ValueCollection\ValueInterval $val
   * @param string $measurement
   */
  public function add(RowCollection\Row $row, ValueCollection\ValueInterval $val, $measurement) {
    $last_row = $this->rowCollection->last();
    if ($last_row === null || $last_row->index() < $row->index()) {
      $this->rowCollection->addRow($row);
    }

    $values = &$this->valueCollections[$measurement];
    for ($i = $values->count(); $i < $this->rowCollection->count() - 1; ++$i) {
      $row = $this->rowCollection->get($i);
      $values->add(0, $row->index(), $row->start(), $row->end());
    }
    $values->add($val->value(), $row->index(), $row->start(), $row->end());
  }

  /**
   * @param Node $selection_node
   * @param array $value_collections
   * @param ValueAggregator $aggregate_func
   * @param int $start_index
   * @param int $end_index
   */
  public function addAggregate(Node $selection_node, array $value_collections, ValueAggregator $aggregate_func, $start_index, $end_index) {
    $node_metadata = get_object_vars($selection_node);
    array_walk($value_collections,
      function(ValueCollection &$values, $measurement)
      use ($start_index, $end_index, $aggregate_func, $selection_node, $node_metadata) {
        $value = $values->aggregate($start_index, $end_index, $aggregate_func);
        $this->addEntry($selection_node->leafIndex, $selection_node->start, $selection_node->end, $value, $measurement,
          $node_metadata, $selection_node->lineageLabel(), $selection_node->lineage());
      });
  }

  /**
   * Bring all measurement value arrays to same index
   */
  public function finalize() {
    $rows = $this->rowCollection;
    array_walk($this->valueCollections, function(ValueCollection &$values, $measurement) use ($rows) {
      for ($i = $values->count(); $i < $rows->count(); ++$i) {
        $row = $rows->get($i);
        $values->add(0, $row->index(), $row->start(), $row->end());
      }
    });
  }

  /**
   * @param int $row
   * @param string $measurement
   * @return float
   */
  public function getValue($row, $measurement) {
    return $this->valueCollections[$measurement]->get($row);
  }

  /**
   * @param int $row
   * @return RowCollection\Row
   */
  public function getRow($row) { return $this->rowCollection->get($row); }

  /**
   * @return int
   */
  public function count() { return $this->rowCollection->count(); }

  /**
   * @param array $selection_nodes
   * @param ValueAggregator $aggregate_func
   * @param int $start
   * @param int $end
   * @return DatasourceTable
   */
  public function aggregate(array $selection_nodes, ValueAggregator $aggregate_func, $start, $end) {
    $ret = DatasourceTable::createEmpty(
      $this->measurements,
      $this->metadataCols,
      $this->levels,
      $this->useOffset,
      $this->storeIndex,
      $this->storeEnd);

    $n = $this->count();
    list($selection_node_id, $selection_node) = each($selection_nodes);

    $last_i = 0;
    for ($i = 0; $i < $n; ++$i) {
      $row = $this->getRow($i);
      while ($selection_node !== null && $row->start() >= $selection_node->end) {
        if ($selection_node->selectionType === SelectionType::NODE && $last_i < $i) {
          $ret->addAggregate($selection_node, $this->valueCollections, $aggregate_func, $last_i, $i);
        }

        list($selection_node_id, $selection_node) = each($selection_nodes);
        $last_i = $i;
      }

      if ($selection_node->end > $row->start() && $selection_node->start < $row->end()) {
        continue;
      }

      array_walk($this->valueCollections, function(ValueCollection &$values, $measurement) use ($ret, $row, $i) {
        $ret->add($row, $values->get($i), $measurement);
      });

      $last_i = $i + 1;
    }

    if ($selection_node !== null && $selection_node->selectionType === SelectionType::NODE &&
      $selection_node->end > $start && $selection_node->start < $end) {
      $ret->addAggregate($selection_node, $this->valueCollections, $aggregate_func, $last_i, $i);
    }

    return $ret;
  }

  /**
   * @param array $order
   * @return DatasourceTable
   */
  public function reorder(array &$order) {
    $rows = $this->rowCollection->reorder($order);
    $vals = array_map(function(ValueCollection $v) use (&$order) {
      return $v->reorder($order);
    }, $this->valueCollections);

    return DatasourceTable::createFromData($rows, $vals);
  }
}
