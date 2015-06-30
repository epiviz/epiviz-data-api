<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/30/2015
 * Time: 3:40 PM
 */

spl_autoload_register(function($class_name) {
  require_once('src/'.str_replace('\\', '/', $class_name).'.php');
});

require_once('src/epiviz/utils/utils.php');
