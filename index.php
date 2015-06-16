<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:17 PM
 */

use Phalcon\Mvc\Micro;

require_once('Config.php');
require_once('EpivizDatabase.php');
require_once('EpivizApiController.php');

use epiviz\api\EpivizApiController;

header('Content-Type: text/json');
header('Access-Control-Allow-Origin: *');

$app = new Micro();
$controller = new EpivizApiController();

//Matches if the HTTP method is GET or POST
$app->map('/rows/{start:[0-9]+}/{end:[0-9]+}[/]?{partition:[0-9a-zA-Z_\.]*}[/]?{metadata:[0-9a-zA-Z_\.,]*}[/]?{retrieve_index}[/]?{retrieve_end}[/]?{offset_location}', array($controller, 'getRows'))->via(array('GET', 'POST'));
$app->map('/values/{measurement:[0-9a-zA-Z_\.]*}/{start:[0-9]+}/{end:[0-9]+}[/]?{partition:[0-9a-zA-Z_]*}', array($controller, 'getValues'))->via(array('GET', 'POST'));
//$app->map('/measurements[/]?{annotation:[0-9a-zA-Z_\.,]*}', array($controller, 'getMeasurements'))->via(array('GET', 'POST'));

$app->handle();
