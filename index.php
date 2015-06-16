<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:17 PM
 */

use Phalcon\Mvc\Micro;

require_once('EpivizApiController.php');
use epiviz\api\EpivizApiController;

$app = new Micro();
$controller = new EpivizApiController();

//Matches if the HTTP method is GET or POST
$app->map('/rows/?{partition}/{start}/{end}/?{metadata}', array($controller, 'getRows'))->via(array('GET', 'POST'));
//$app->map('/values', array($controller, 'getGetValues'))->via(array('GET', 'POST'));

$app->handle();
