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
   * @var array
   */
  private $intervals;

  public function __construct(array $order_nodes) {
    $this->orderNodes = $order_nodes;

    $this->buildTree();
  }

  public function &intervalRoot() { return $this->intervalRoot; }

  public function &intervals() { return $this->intervals; }

  private function buildTree() {
    $interval_boundaries = array();
    foreach ($this->orderNodes as &$node) {
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
    $node_stack[] = &$interval_root;
    for ($i = 0; $i < $n; ++$i) {
      $interval = null;
      $val = $interval_boundaries[$i]->boundary;
      $interval_node = &$node_stack[count($node_stack) - 1];
      if ($i == 0) {
        $filler_node = new IntervalNode(null, $val);
        $filler_node->parent = &$interval_node;
        $interval_node->children[] = $filler_node;
        $interval = new SimpleInterval(null, $val, $interval_node->children[0]);
      } else {
        if ($val > $last_interval->end) {
          if ($interval_boundaries[$i]->intervalNode === $interval_boundaries[$i-1]->intervalNode) {
            $interval = new SimpleInterval($last_interval->end, $val, $interval_node);
          } else {
            $filler_node = new IntervalNode($last_interval->end, $val);
            $filler_node->parent = &$interval_node;
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
        $node_stack[] = &$interval_boundaries[$i]->intervalNode;
        $interval_node->children[] = &$interval_boundaries[$i]->intervalNode;
        $interval_boundaries[$i]->intervalNode->parent = &$interval_node;
      } else {
        array_pop($node_stack);
      }
    }
    $filler_node = new IntervalNode($last_interval->end, null);
    $filler_node->parent = &$interval_root;
    $interval_root->children[] = $filler_node;
    $interval = new SimpleInterval($last_interval->end, null, $interval_root->children[count($interval_root->children) - 1]);
    $intervals[] = $interval;

    $this->intervalRoot = &$interval_root;
    $this->intervals = &$intervals;
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
   */
  public function __construct($start=null, $end=null, Node &$data=null) {
    $this->data = &$data;
    $this->start = $start;
    $this->end = $end;
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
  public function __construct($start, $end, IntervalNode &$interval_node) {
    $this->start = $start;
    $this->end = $end;
    $this->intervalNode = &$interval_node;
  }
}
