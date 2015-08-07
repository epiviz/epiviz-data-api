<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/29/2015
 * Time: 4:19 PM
 */

namespace epiviz\models;

/**
 * Class Node
 * @package epiviz\models
 */
class Node {
  public $id;
  public $name;
  public $label;
  public $globalDepth;
  public $depth;
  public $taxonomy;
  public $parentId;
  public $nchildren;
  public $size;
  public $start;
  public $end;
  public $partition;
  public $leafIndex;
  public $nleaves;
  public $order;
  public $selectionType;
  public $children;

  private $lineage;
  private $lineageLabel;

  public function __construct($id, $label, $depth, $taxonomy, $parent_id, $nchildren, $children, $partition, $start, $end, $leaf_index, $nleaves, $order, $lineage, $lineage_label, $selection_type=SelectionType::LEAVES) {
    $this->id = $id;
    $this->label = $label;
    $this->name = $label;
    $this->globalDepth = $depth;
    $this->depth = $depth;
    $this->taxonomy = $taxonomy;
    $this->parentId = $parent_id;
    $this->nchildren = $nchildren;
    $this->children = $children;
    $this->size = 1;
    $this->start = $start;
    $this->end = $end;
    $this->leafIndex = $leaf_index;
    $this->nleaves = $nleaves;
    $this->order = $order;
    $this->lineage = $lineage;
    $this->lineageLabel = $lineage_label;
    $this->selectionType = $selection_type;
  }

  public function lineage() { return $this->lineage; }

  public function lineageLabel() { return $this->lineageLabel; }

  public function parentLabel() {
    $lineageLabels = explode(',', $this->lineageLabel);
    if ($this->depth >= 1) {
      return $lineageLabels[$this->depth - 1];
    }
    return null;
  }

  public function ancestorId($depth) {
    if ($depth > $this->depth || $depth < 0) { return null; }
    $lineageIds = explode(',', $this->lineage);
    return $lineageIds[$depth];
  }

  public function ancestorLabel($depth) {
    if ($depth > $this->depth || $depth < 0) { return null; }
    $lineageLabels = explode(',', $this->lineageLabel);
    return $lineageLabels[$depth];
  }
}