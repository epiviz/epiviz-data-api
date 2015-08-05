<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:17 PM
 */

require_once('autoload.php');

use epiviz\api\EpivizApiController;

use epiviz\api\ValueAggregatorFactory;
use epiviz\api\Average;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$value_aggregator_factory = new ValueAggregatorFactory();
$value_aggregator_factory->register(new Average());

$api_controller = new EpivizApiController($value_aggregator_factory);
$req_controller = new \epiviz\api\EpivizRequestController($_REQUEST);

$req_controller->registerMethod(
  'aggregatingFunctions',
  array(),
  'string',
  array($api_controller, 'getAggregatingFunctions'),
  array(
    'request' => 'method=aggregatingFunctions',
    'response' => function() use ($api_controller) { return $api_controller->getAggregatingFunctions(); }
  ));

$req_controller->registerMethod(
  'nodes',
  array('nodeIds' => 'array'),
  'object',
  array($api_controller, 'getNodes'),
  array(
    'request' => 'method=nodes&params[]=["1-0","1-1"]',
    'response' => function() use ($api_controller) { return $api_controller->getNodes(array('1-0', '1-1')); }
  ));

$req_controller->registerMethod(
  'siblings',
  array('nodeIds' => 'array'),
  'object',
  array($api_controller, 'getSiblings'),
  array(
    'request' => 'method=siblings&params[]=["1-0","2-26","0-0"]',
    'response' => function() use ($api_controller) { return $api_controller->getSiblings(array('1-0', '2-26', '0-0')); }
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
    'request' => 'method=rows&params[start]=0&params[end]=130&params[selection]={"2-26":2,"2-7d6":0}&params[order]={"2-26":-1}',
    'response' => function() use ($api_controller) { return $api_controller->getRows(0, 130, null, null, true, true, false, array('2-26'=>2, '2-7d6'=>0), array('2-26'=>-1)); }
  ));

$req_controller->registerMethod(
  'values',
  array(
    'measurement' => 'string',
    'start' => 'number',
    'end' => 'number',
    'partition' => array('type' => 'string', 'optional' => true, 'default' => null),
    'selection' => array('type' => 'object', 'optional' => true, 'default' => null),
    'order' => array('type' => 'object', 'optional' => true, 'default' => null),
    'aggregationFunction' => array('type' => 'string', 'optional' => true, 'default' => 'average')
  ),
  array(
    'globalStartIndex' => 'number',
    'values' => 'array'),
  array($api_controller, 'getValues'),
  array(
    'request' => 'method=values&params[measurement]="700014391.V35.241827"&params[start]=2739&params[end]=2742&params[selection]={"2-26":0,"2-7db":1}&params[order]={"8-ab4":-1}',
    'response' => function() use ($api_controller) { return $api_controller->getValues('700014391.V35.241827', 2739, 2742, null, array('2-26'=>0, '2-7db'=>1), array('8-ab4'=>-1)); }
  ));

$req_controller->registerMethod(
  'measurements',
  array(
    'maxCount' => array('type' => 'number', 'optional' => true, 'default' => null),
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
    'response' => function() use ($api_controller) { return $api_controller->getMeasurements(2, array('country', 'index')); }
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
  array($api_controller, 'getHierarchy'),
  array(
    'request' => 'method=hierarchy&params[depth]=1&params[nodeId]="1-1"&params[order]={"2-26":-1,"2-7f":34}',
    'response' => function() use ($api_controller) { return $api_controller->getHierarchy(1, '1-1', null, array('2-26'=>-1, '2-7f'=>34)); }
  ));

$req_controller->registerMethod(
  'hierarchies',
  array(
    'depth' => 'number',
    'nodeIds' => 'array',
    'selection' => array('type' => 'object', 'optional' => true, 'default' => null),
    'order' => array('type' => 'object', 'optional' => true, 'default' => null)
  ),
  'object',
  array($api_controller, 'getHierarchies'),
  array(
    'request' => 'method=hierarchies&params[nodeIds]=["1-0","1-1"]&params[depth]=2',
    'response' => function() use ($api_controller) { return $api_controller->getHierarchies(2, array('1-0', '1-1')); }
  ));

$req_controller->registerMethod(
  'partitions',
  array(),
  'array',
  array($api_controller, 'getPartitions'),
  array(
    'request' => 'method=partitions',
    'response' => function() use ($api_controller) { return $api_controller->getPartitions(); }
  ));

$req_controller->registerMethod(
  'levels',
  array(),
  'array',
  array($api_controller, 'getLevels'),
  array(
    'request' => 'method=levels',
    'response' => function() use ($api_controller) { return $api_controller->getLevels(); }
  ));

$req_controller->handle($_REQUEST);
