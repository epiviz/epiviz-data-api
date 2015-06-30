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
  public $children;

  private $lineage;

  public function __construct($id, $label, $depth, $taxonomy, $parent_id, $nchildren, $children, $partition, $start, $end, $leaf_index, $nleaves, $order, $lineage) {
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
  }

  public function lineage() { return $this->lineage; }
}