<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 7/4/2015
 * Time: 1:03 PM
 */

namespace epiviz\models;

/**
 * TODO: Use in a future getCombined method
 * Class DatasourceTable
 * @package epiviz\models
 */
class DatasourceTable {
  public $rows;
  public $cols;

  private $rowCollection;
  private $valueCollections = array();

  /**
   * @param array $measurements
   * @param array $metadata_cols
   * @param array $levels
   * @param bool $use_offset
   * @param bool $store_index
   * @param bool $store_end
   */
  public function __construct(array $measurements = array(), array $metadata_cols = null, array $levels, $use_offset = false, $store_index = true, $store_end = true) {
    $this->rowCollection = new RowCollection($metadata_cols, $levels, $use_offset, $store_index, $store_end);
    $this->rows = &$this->rowCollection->values;

    $value_collections = array_flip($measurements);
    array_walk($value_collections, function(&$v, $m) { $v = new ValueCollection(); });
    $this->valueCollections = $value_collections;

    $cols = array();
    array_walk($value_collections, function(ValueCollection &$val_col, $m) use (&$cols) {
      $cols[] = array('id' => $m, 'values' => &$val_col->values);
    });
  }
}
