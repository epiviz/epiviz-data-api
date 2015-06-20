<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:17 PM
 */

require_once('util.php');
require_once('Config.php');
require_once('EpivizDatabase.php');
require_once('SelectionType.php');
require_once('EpivizApiController.php');
require_once('EpivizRequestController.php');

use epiviz\api\EpivizApiController;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$api_controller = new EpivizApiController();
$req_controller = new \epiviz\api\EpivizRequestController($_REQUEST);

$req_controller->registerMethod(
  'nodes',
  array('nodeIds' => 'array'),
  'object',
  array($api_controller, 'getNodes'),
  array(
    'request' => 'method=nodes&params[]=["1-0","1-1"]',
    'response' => json_decode('{"1-0":{"id":"1-0","name":"Archaea","globalDepth":1,"depth":1,"taxonomy":"kingdom","parentId":"0-0","nchildren":1,"size":1,"leafIndex":0,"nleaves":1},"1-1":{"id":"1-1","name":"Bacteria","globalDepth":1,"depth":1,"taxonomy":"kingdom","parentId":"0-0","nchildren":34,"size":1,"leafIndex":1,"nleaves":14784}}')
  ));

$req_controller->registerMethod(
  'rows',
  array(
    'start' => 'number',
    'end' => 'number',
    'partition' => array('type' => 'string', 'optional' => true, 'default' => null),
    'metadata' => array('type' => 'array', 'optional' => true, 'default' => null),
    'retrieve_index' => array('type' => 'boolean', 'optional' => true, 'default' => true),
    'retrieve_end' => array('type' => 'boolean', 'optional' => true, 'default' => true),
    'offset_location' => array('type' => 'boolean', 'optional' => true, 'default' => false),
    'selection' => array('type' => 'object', 'optional' => true, 'default' => null),
    'order' => array('type' => 'object', 'optional' => true, 'default' => null)
  ),
  array(
    'globalStartIndex' => 'number',
    'useOffset' => 'boolean',
    'values' => array(
      'index' => 'array',
      'start' => 'array',
      'end' => 'array',
      'metadata' => 'object')),
  array($api_controller, 'getRows'),
  array(
    'request' => 'method=rows&params[start]=15&params[end]=17',
    'response' => json_decode('{"values":{"index":[14,15,16],"start":[14,15,16],"end":[15,16,17],"metadata":{"id":["8-e","8-f","8-10"],"label":["219134","237664","241895"]}},"globalStartIndex":14,"useOffset":false}')
  ));

$req_controller->registerMethod(
  'values',
  array(
    'measurement' => 'string',
    'start' => 'number',
    'end' => 'number',
    'partition' => array('type' => 'string', 'optional' => true, 'default' => null)
  ),
  array(
    'globalStartIndex' => 'number',
    'values' => 'array'),
  array($api_controller, 'getValues'),
  array(
    'request' => 'method=values&params[measurement]="700014391.V35.241827"&params[start]=2739&params[end]=2742',
    'response' => json_decode('{"globalStartIndex":2738,"values":[0,76.923,0,76.923]}')
  ));

$req_controller->registerMethod(
  'measurements',
  array(
    'maxCount' => array('type' => 'number', 'optional' => true, 'default' => 0),
    'annotation' => array('type' => 'array', 'optional' => true, 'default' => null)
  ),
  array(
    'id' => 'array',
    'name' => 'array',
    'type' => 'string',
    'datasourceId' => 'string',
    'datasourceGroup' => 'string',
    'defaultChartType' => 'string',
    'annotation' => 'array',
    'minValue' => 'number',
    'maxValue' => 'number',
    'metadata' => 'array'),
  array($api_controller, 'getMeasurements'),
  array(
    'request' => 'method=measurements&params[maxCount]=2&params[annotation]=["country","index"]',
    'response' => json_decode('{"id":["700014390.V35.241827","700014391.V35.241827"],"name":[700014390,700014391],"type":"feature","datasourceId":"hmp","datasourceGroup":"hmp","defaultChartType":"","annotation":[{"country":"GAZ:United States of America","index":62},{"country":"GAZ:United States of America","index":5800}],"minValue":1.4880952380952,"maxValue":2420000.0000079,"metadata":["id","label"]}', true)
  )
);

$req_controller->registerMethod(
  'hierarchy',
  array(
    'depth' => 'number',
    'nodeId' => array('type' => 'string', 'optional' => true, 'default' => null),
    'selection' => array('type' => 'object', 'optional' => true, 'default' => null),
    'order' => array('type' => 'object', 'optional' => true, 'default' => null)
  ),
  array(
    'id' => 'string',
    'name' => 'string',
    'globalDepth' => 'number',
    'depth' => 'number',
    'taxonomy' => 'string',
    'nchildren' => 'number',
    'size' => 'number',
    'selectionType' => 'number',
    'nleaves' => 'number',
    'children' => 'array'
  ),
  array($api_controller, 'getHierarchy'));

$req_controller->registerMethod(
  'hierarchies',
  array(
    'depth' => 'number',
    'nodeIds' => 'array',
    'order' => array('type' => 'object', 'optional' => true, 'default' => null)
  ),
  'object',
  array($api_controller, 'getHierarchies'));

$req_controller->handle($_REQUEST);

/*$n = array(
  'order' => 0,
  'children' => array(
    array(
      'order' => 2
    ),
    array(
      'order' => 1
    )
  )
);

EpivizApiController::dfs($n, function(&$node) {
  if (!array_key_exists('children', $node)) { return; }
  $children = &$node['children'];
  usort($children, function($c1, $c2) {
    if (!array_key_exists('order', $c1)) { print_r($c1); }
    if (!array_key_exists('order', $c2)) { print_r($c2); }
    return $c1['order'] - $c2['order'];
  });
});

print_r($n);

*/
