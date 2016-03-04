<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/17/2015
 * Time: 10:02 AM
 */

namespace epiviz\api;

use Exception;

class EpivizRequestController {
  private $methodsMap = array();
  private $methods = array();
  private $handlers = array();

  public function __construct() {
    $self = $this;
    $this->registerMethod(
      'show',
      array('method' => array('type' => 'string', 'optional' => true, 'default' => null)),
      'array',
      array($this, 'show'),
      array(
        'request' => 'method=show&params[method]="help"',
        'response' => function() use ($self) { return $self->show('help'); }));

    $this->registerMethod(
      'help',
      array(),
      array('methods' => 'array', 'exampleUsage' => 'string'),
      array($this, 'help'),
      array('request' => 'method=help', 'response' => function() use ($self) { return $self->help(); })
      );
  }

  public function help() {
    $methods = array_map(function($def) { return $def['method']; }, $this->methods);
    sort($methods);
    return array('methods' => $methods, 'exampleUsage' => 'method=show&params[method]="help"');
  }

  public function show($method=null) {
    if ($method === null || !array_key_exists($method, $this->methodsMap)) {
      $methods = $this->methods;
      usort($methods, function($m1, $m2) {
        $n1 = $m1['method'];
        $n2 = $m2['method'];
        if ($n1 == $n2) { return 0; }
        return ($n1 > $n2) ? +1 : -1;
      });

      array_walk($methods, function(&$m) {
        $m['example']['response'] = $m['example']['response']();
      });

      return $methods;
    } else {
      $m = $this->methods[$this->methodsMap[$method]];
      $m['example']['response'] = $m['example']['response']();
      return $m;
    }
  }

  public function registerMethod($name, array $parameter_defs, $response_def, $handler, $example=null) {
    $this->methodsMap[$name] = count($this->methods);
    $def = array('method' => $name, 'params' => $parameter_defs, 'result' => $response_def);
    if ($example !== null) { $def['example'] = $example; }
    $this->methods[] = $def;
    $this->handlers[] = $handler;
  }

  private function prepareParams($method_def, $params) {
    $decoded = false;
    if ($params === null) { $params = array(); }
    if (!is_array($params)) {
      $decoded_params = json_decode($params, true);
      if (is_array($decoded_params) && is_assoc($decoded_params)) {
        $params = $decoded_params;
        $decoded = true;
      } else {
        $params = array($params);
      }
    }
    if (!is_assoc($params)) {
      $params_assoc = array();
      $i = 0;
      foreach ($method_def['params'] as $name => $def) {
        if ($i >= count($params)) { break; }
        $params_assoc[$name] = $params[$i];
        ++$i;
      }
      $params = $params_assoc;
    }
    $ret = array();
    foreach ($method_def['params'] as $name => $def) {
      $type = null;
      $optional = false;
      $default = null;
      if (!is_array($def)) {
        $type = $def;
      } else {
        $type = $def['type'];
        $optional = idx($def, 'optional', false);
        $default = idx($def, 'default');
      }

      if (!array_key_exists($name, $params)) {
        if (!$optional) {
          throw new Exception('Undefined value for non-optional parameter \''.$name.'\'.');
        }

        $ret[] = $default;
      } else {
        $json_val = $params[$name];
        $val = ($decoded) ? $json_val : json_decode($params[$name], true);
        if (is_array($val) && empty($val)) { $val = null; }
        else if (!$decoded &&
                json_encode($val) != $json_val &&
                json_encode($val, JSON_FORCE_OBJECT) != $json_val) {
          throw new Exception('Error parsing value of parameter \''.$name.'\': \''.$json_val.'\'.');
        }
        switch ($type) {
          case 'number':
            if ($val !== null && !is_numeric($val)) { throw new Exception('Invalid value for parameter of type \'number\': \''.$name.'\' = '.$json_val.'.'); }
            $val = floatval($val);
            break;
          case 'string':
            if ($val !== null && !is_string($val)) { throw new Exception('Invalid value for parameter of type \'string\': \''.$name.'\' = '.$json_val.'.'); }
            break;
          case 'boolean':
            if ($val !== null && !is_bool($val)) { throw new Exception('Invalid value for parameter of type \'boolean\': \''.$name.'\' = '.$json_val.'.'); }
            $val = boolval($val);
            break;
          case 'array':
            if ($val !== null && (!is_array($val) || is_assoc($val))) { throw new Exception('Invalid value for parameter of type \'array\': \''.$name.'\' = '.$json_val.'.'); }
            break;
          case 'object':
            if ($val !== null && (!is_array($val) || !is_assoc($val))) { throw new Exception('Invalid value for parameter of type \'object\': \''.$name.'\' = '.$json_val.'.'); }
            break;
        }

        $ret[] = $val;
      }
    }

    return $ret;
  }

  public function handle($args) {
    $id = idx($args, 'id');
    try {
      if (!array_key_exists('method', $args)) {
        throw new Exception('Expected parameter: \'method\' not found.');
      }

      $method = $args['method'];
      if (!array_key_exists($method, $this->methodsMap)) {
        throw new Exception('Method "'.$method.'" not found.');
      }

      $params = idx($args, 'params');
      $params = $this->prepareParams($this->methods[$this->methodsMap[$method]], $params);

      $result = call_user_func_array($this->handlers[$this->methodsMap[$method]], $params);

      $ret = array('id' => $id, 'error' => null, 'result' => $result);
    } catch (Exception $e) {
      $ret = array(
        'id' => $id,
        'error' => $e->getMessage(),
        'result' => $this->help()
      );
    }

    echo json_encode($ret);
  }
}