<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/29/2015
 * Time: 4:06 PM
 */

namespace epiviz\utils;

use epiviz\models\Node;
use epiviz\utils\OrderedIntervalTree\IntervalNode;
use epiviz\utils\OrderedIntervalTree\SimpleInterval;
use epiviz\utils\OrderedIntervalTree\IntervalBoundary;

/**
 * Class OrderedIntervalTree
 * @package epiviz\utils
 */
class OrderedIntervalTree {
  /**
   * @var array
   */
  private $orderNodes;

  /**
   * @var IntervalNode
   */
  private $intervalRoot;

  /**
   * @var IntervalNode
   */
  //private $orderedIntervalRoot;

  /**
   * @var array
   */
  private $intervals;

  /**
   * @var array
   */
  //private $orderedIntervals;

  /**
   * @var bool
   */
  private $dirty = false;

  public function __construct(array $order_nodes) {
    $this->orderNodes = $order_nodes;

    $this->buildTree();
  }

  public function intervalRoot() { return $this->intervalRoot; }

  public function &intervals() { return $this->intervals; }

  private function buildTree() {
    $interval_boundaries = array();
    foreach ($this->orderNodes as $node) {
      $interval_node = new IntervalNode($node->leafIndex, $node->leafIndex + $node->nleaves, $node);
      $interval_boundaries[] = new IntervalBoundary($interval_node->start, true, $interval_node);
      $interval_boundaries[] = new IntervalBoundary($interval_node->end, false, $interval_node);
    }
    usort($interval_boundaries, function($e1, $e2) {
      $ret = $e1->boundary - $e2->boundary;
      if ($ret == 0) {
        if ($e1->isStart xor $e2->isStart) {
          $ret = ($e1->isStart) ? 1 : -1;
        } else {
          $ret = $e1->intervalNode->data->depth - $e2->intervalNode->data->depth;
        }
      }
      return $ret;
    });

    $n = count($interval_boundaries);
    $node_stack = array();
    $intervals = array();
    $last_interval = null;
    $interval_root = new IntervalNode();
    $node_stack[] = $interval_root;
    for ($i = 0; $i < $n; ++$i) {
      $interval = null;
      $val = $interval_boundaries[$i]->boundary;
      $interval_node = $node_stack[count($node_stack) - 1];
      if ($i == 0) {
        $filler_node = new IntervalNode(null, $val);
        $filler_node->parent = $interval_node;
        $interval_node->children[] = $filler_node;
        $interval = new SimpleInterval(null, $val, $interval_node->children[0]);
      } else {
        if ($val > $last_interval->end) {
          if ($interval_boundaries[$i]->intervalNode === $interval_boundaries[$i-1]->intervalNode) {
            $interval = new SimpleInterval($last_interval->end, $val, $interval_node);
          } else {
            $filler_node = new IntervalNode($last_interval->end, $val);
            $filler_node->parent = $interval_node;
            $interval_node->children[] = $filler_node;
            $interval = new SimpleInterval($last_interval->end, $val, $interval_node->children[count($interval_node->children) - 1]);
          }
        }
      }
      if ($interval !== null) {
        $intervals[] = $interval;
        $last_interval = $interval;
      }

      if ($interval_boundaries[$i]->isStart) {
        $node_stack[] = $interval_boundaries[$i]->intervalNode;
        $interval_node->children[] = $interval_boundaries[$i]->intervalNode;
        $interval_boundaries[$i]->intervalNode->parent = $interval_node;
      } else {
        array_pop($node_stack);
      }
    }
    $filler_node = new IntervalNode($last_interval->end, null);
    $filler_node->parent = $interval_root;
    $interval_root->children[] = $filler_node;
    $interval = new SimpleInterval($last_interval->end, null, $interval_root->children[count($interval_root->children) - 1]);
    $intervals[] = $interval;

    $this->intervalRoot = $interval_root;
    $this->intervals = &$intervals;

    // Looks like we will not need these
    /*$this->orderedIntervalRoot = OrderedIntervalTree::copyIntervalHierarchy($interval_root);
    $this->orderTreeNodes($this->orderedIntervalRoot);
    $ordered_intervals = array();
    $dfs = function(IntervalNode $node) use (&$dfs, &$ordered_intervals) {
      if (empty($node->children)) {
        $ordered_intervals[] = new SimpleInterval($node->start, $node->end, $node);
        return;
      }
      array_walk($node->children, $dfs);
    };
    $dfs($this->orderedIntervalRoot);
    $this->orderedIntervals = $ordered_intervals;*/
  }

  private static function orderTreeNodes(IntervalNode $root) {
    if (empty($root->children)) { return; }

    $start = $root->start;

    $nodes_by_parent = array();
    array_walk($root->children, function(IntervalNode &$node) use (&$nodes_by_parent) {
      if (!$node->data) { return; }
      $parent = $node->data->parentId;
      if (!array_key_exists($parent, $nodes_by_parent)) {
        $nodes_by_parent[$parent] = array();
      }
      $nodes_by_parent[$parent][] = $node;
    });

    array_walk($nodes_by_parent, function(array &$nodes) {
      $unsorted = $nodes;
      usort($nodes, function(IntervalNode $n1, IntervalNode $n2) {
        return $n1->data->order - $n2->data->order;
      });

      $n = count($nodes);
      for ($i = 0; $i < $n; ++$i) {
        $nodes[$i]->start = $unsorted[$i]->originalStart;
      }
    });

    usort($root->children, function(IntervalNode $n1, IntervalNode $n2) {
      return $n1->start - $n2->start;
    });

    array_walk($root->children, function(IntervalNode $node) use (&$start) {
      $node->start = $start;
      $node->end = $start + $node->originalEnd - $node->originalStart;
      $start = $node->end;
    });

    array_walk($root->children, function(IntervalNode $node) {
      OrderedIntervalTree::orderTreeNodes($node);
    });
  }

  private static function copyIntervalHierarchy(IntervalNode $node) {
    $copy = new IntervalNode(
      $node->start,
      $node->end,
      $node->data,
      $node->parent);
    $copy->originalStart = $node->originalStart;
    $copy->originalEnd = $node->originalEnd;
    $copy->children = array_map(function($child) { return OrderedIntervalTree::copyIntervalHierarchy($child); }, $node->children);
    $copy->indices = $node->indices;
    return $copy;
  }

  public function orderIntervals(array &$intervals) {
    if ($this->dirty) {
      // Clear indices in tree
      $dfs = null;
      $dfs = function(IntervalNode $node) use (&$dfs) {
        $node->indices = array();
        array_walk($node->children, $dfs);
      };
      $dfs($this->intervalRoot);
    }
    $this->dirty = true;

    $interval = null;
    $find_node_container = function(IntervalNode $node) use (&$interval, &$find_node_container) {
      if (($node->originalStart !== null && $node->originalStart > $interval->start) ||
        ($node->originalEnd !== null && $node->originalEnd < $interval->end)) {
        return null;
      }
      $ret = $node;
      $i = binary_search($interval, $node->children, function(IntervalNode $child) use (&$interval) {
        if ($child->originalStart !== null && $interval->end <= $child->originalStart) { return -1; }
        if ($child->originalEnd !== null && $interval->start >= $child->originalEnd) { return 1; }

        // Overlap
        return 0;
      });
      if ($i >= 0) {
        $candidate = $find_node_container($node->children[$i]);
        if ($candidate !== null) { $ret = $candidate; }
      }
      return $ret;
    };

    $last_node = null;
    foreach ($intervals as $index => $interval) {
      $node_container = null;
      if ($last_node !== null) {
        $node_container = $find_node_container($last_node);
      }

      if ($node_container === null) {
        $node_container = $find_node_container($this->intervalRoot);
      }
      $node_container->indices[] = $index;
    }

    //
    $root = OrderedIntervalTree::copyIntervalHierarchy($this->intervalRoot);
    $this->orderTreeNodes($root);
    $ordered_interval_nodes = array();
    $dfs = function(IntervalNode $node) use (&$dfs, &$ordered_interval_nodes) {
      if (empty($node->children)) {
        $ordered_interval_nodes[] = new SimpleInterval($node->start, $node->end, $node);
        return;
      }
      array_walk($node->children, $dfs);
    };
    $dfs($root);
    //

    $ordered_intervals = array();
    array_walk($ordered_interval_nodes, function(SimpleInterval $simple_interval) use (&$intervals, &$ordered_intervals) {
      foreach ($simple_interval->intervalNode->indices as $i) {
        $ordered_intervals[] = &$intervals[$i];
      }
    });
    return $ordered_intervals;
  }

  public function rawTree() {
    if ($this->intervalRoot == null) { return array(); }

    $dfs = null;
    $dfs = function(IntervalNode $node) use (&$dfs) {
      return array(
        'name'=> $node->data ? $node->data->label : '$',
        'start' => $node->start,
        'end' => $node->end,
        'children' => array_map($dfs, $node->children)
      );
    };

    return $dfs($this->intervalRoot);
  }

  public function rawIntervals() {
    return array_map(function($interval) {
      return array(
        'belongsTo' => $interval->intervalNode->data ? $interval->intervalNode->data->label : '$',
        'start' => $interval->start,
        'end' => $interval->end);
    },
    $this->intervals);
  }

  /*public function rawOrderedIntervals() {
    return array_map(function($interval) {
      return array(
        'belongsTo' => $interval->intervalNode->data ? $interval->intervalNode->data->label : '$',
        'start' => $interval->start,
        'end' => $interval->end);
    },
    $this->orderedIntervals);
  }*/
}

namespace epiviz\utils\OrderedIntervalTree;

use epiviz\models\Node;

/**
 * Class IntervalNode
 * @package epiviz\utils\OrderedIntervalTree
 */
class IntervalNode {
  /**
   * @var Node
   */
  public $data;

  /**
   * @var int
   */
  public $start;

  /**
   * @var int
   */
  public $end;

  /**
   * @var int
   */
  public $originalStart;

  /**
   * @var int
   */
  public $originalEnd;

  /**
   * @var IntervalNode
   */
  public $parent;

  /**
   * @var array
   */
  public $children = array();

  /**
   * @var array
   */
  public $indices = array();

  /**
   * @param int $start Used only if data is not defined
   * @param int $end Used only if data is not defined
   * @param Node $data
   * @param IntervalNode $parent
   */
  public function __construct($start=null, $end=null, Node $data=null, IntervalNode $parent=null) {
    $this->start = $start;
    $this->end = $end;
    $this->data = $data;
    $this->parent = $parent;

    $this->originalStart = $start;
    $this->originalEnd = $end;
  }
}

/**
 * Class IntervalBoundary
 * @package epiviz\utils\OrderedIntervalTree
 */
class IntervalBoundary {
  /**
   * @var int
   */
  public $boundary;

  /**
   * @var bool
   */
  public $isStart;

  /**
   * @var IntervalNode
   */
  public $intervalNode;

  /**
   * @param int $boundary
   * @param bool $is_start
   * @param IntervalNode $interval_node
   */
  public function __construct($boundary, $is_start, IntervalNode $interval_node) {
    $this->boundary = $boundary;
    $this->isStart = $is_start;
    $this->intervalNode = $interval_node;
  }
}

class SimpleInterval {
  /**
   * @var int
   */
  public $start;

  /**
   * @var int
   */
  public $end;

  /**
   * @var IntervalNode
   */
  public $intervalNode;

  /**
   * @param int $start
   * @param int $end
   * @param IntervalNode $interval_node
   */
  public function __construct($start, $end, IntervalNode $interval_node) {
    $this->start = $start;
    $this->end = $end;
    $this->intervalNode = $interval_node;
  }
}
