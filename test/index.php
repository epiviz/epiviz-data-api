<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/30/2015
 * Time: 6:23 PM
 */

namespace epiviz\utils;

require_once('../autoload.php');

use epiviz\models\Node;
use epiviz\models\SimpleIntervalCollection;
use epiviz\utils\OrderedIntervalTree\SimpleInterval;

class OrderedIntervalTreeTest {
  public function testBuildTreeHierarchy() {

    $order_nodes = array(
      new Node('B', 'B', 6, null, 'C', 0, null, null, null, null, 9, 3, -1, null),
      new Node('C', 'C', 4, null, 'Z', 10, null, null, null, null, 4, 9, 0, null),
      new Node('G', 'G', 2, null, 'Q', 10, null, null, null, null, 28, 2, 0, null),
      new Node('A', 'A', 6, null, 'C', 10, null, null, null, null, 4, 2, 0, null),
      new Node('D', 'D', 1, null, 'X', 10, null, null, null, null, 4, 20, 0, null),
      new Node('E', 'E', 2, null, 'Y', 10, null, null, null, null, 17, 3, 0, null),
      new Node('F', 'F', 2, null, 'M', 10, null, null, null, null, 20, 2, 0, null)
    );

    $t = new OrderedIntervalTree($order_nodes);
    //print_r(json_encode($t->rawOrderedIntervals()));
    $intervals = array();
    for ($i = 5; $i < 15; ++$i) {
      $intervals[] = new SimpleInterval($i, $i + 1);
    }
    print_r($intervals);

    $reordered = $t->orderIntervals(new SimpleIntervalCollection($intervals));
    for ($i = 0; $i < $reordered->count(); ++$i) {
      print_r($reordered->get($i));
      print_r("\n");
    }
  }

  public function testBuildTreeIntervals() {

    $order_nodes = array(
      new Node('B', 'B', 6, null, 'C', 0, null, null, null, null, 9, 2, 0, null),
      new Node('C', 'C', 4, null, 'Z', 10, null, null, null, null, 4, 9, 0, null),
      new Node('G', 'G', 2, null, 'Q', 10, null, null, null, null, 28, 2, 0, null),
      new Node('A', 'A', 6, null, 'C', 10, null, null, null, null, 4, 2, 0, null),
      new Node('D', 'D', 1, null, 'X', 10, null, null, null, null, 4, 20, 0, null),
      new Node('E', 'E', 2, null, 'Y', 10, null, null, null, null, 17, 3, 0, null),
      new Node('F', 'F', 2, null, 'M', 10, null, null, null, null, 20, 2, 0, null)
    );

    $t = new OrderedIntervalTree($order_nodes);
    print_r(json_encode($t->rawIntervals()));
  }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$testSuite = new OrderedIntervalTreeTest();
//$testSuite->testBuildTreeIntervals();
$testSuite->testBuildTreeHierarchy();
