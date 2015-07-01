<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:56 PM
 */

namespace epiviz\api;

use epiviz\models\RowCollection;
use PDO;
use epiviz\models\Node;
use epiviz\models\SelectionType;

/**
 * Class EpivizApiController
 * @package epiviz\api
 */
class EpivizApiController {

  const ROWS_TABLE = 'row_data';
  const VALUES_TABLE = 'values';
  const COLS_TABLE = 'col_data';
  const HIERARCHY_TABLE = 'hierarchy';
  const LEVELS_TABLE = 'levels';

  private $rowsQueryFormat;
  private $valsQueryFormat;
  private $colsQueryFormat;
  private $hierarchyQueryFormat;
  private $nodesQueryFormat;
  private $nodesOrderBy;
  private $db;
  private $tablesColumns = array();
  private $minVal = null;
  private $maxVal = null;

  public function __construct() {
    $this->intervalQueryFormat = '(`index` BETWEEN '
      .'(SELECT MIN(`index`) FROM %1$s WHERE %2$s AND `end` > ? AND `start` < ?) AND '
      .'(SELECT MAX(`index`) FROM %1$s WHERE %2$s AND `end` > ? AND `start` < ?)) ';

    $this->rowsQueryFormat = 'SELECT %1$s FROM %2$s WHERE %3$s ORDER BY `index` ASC ';
//      'SELECT %1$s FROM %2$s WHERE `index` BETWEEN '
//      .'(SELECT MIN(`index`) FROM %2$s WHERE %3$s AND `start` < :end1 AND `end` >= :start1) AND '
//      .'(SELECT MAX(`index`) FROM %2$s WHERE %4$s AND `start` < :end2 AND `end` >= :start2) ORDER BY `index` ASC ';

    $this->valsQueryFormat =
      'SELECT `val`, `%1$s`.`index` FROM `%1$s` LEFT OUTER JOIN '
        .'(SELECT `val`, `row`, `col` FROM `%2$s` JOIN `%3$s` ON `col` = `index` WHERE `%3$s`.`id` = :measurement) vals '
        .'ON vals.`row` = `%1$s`.`index` '
      .'WHERE `%1$s`.`index` BETWEEN '
        .'(SELECT MIN(`index`) FROM `%1$s` WHERE %4$s AND `start` < :end1 AND `end` >= :start1) AND '
        .'(SELECT MAX(`index`) FROM `%1$s` WHERE %5$s AND `start` < :end2 AND `end` >= :start2) '
      .'ORDER BY `%1$s`.`index` ASC ';

    $this->colsQueryFormat =
      'SELECT %1$s FROM %2$s ORDER BY `id` ASC %3$s ';

    $this->hierarchyQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE `lineage` LIKE ? AND `%1$s`.`depth` <= ? ';

    $this->nodesQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineage` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE `id` IN (%3$s) ';

    $this->siblingsQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineage` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE `parentId` IN '
      .'(SELECT `parentId` FROM `%1$s` WHERE `id` IN (%3$s)) OR `id` IN (%3$s) ';

    $this->nodesOrderBy = 'ORDER BY `depth`, `partition`, `start`, `end` ';

    $this->db = (new EpivizDatabase())->db();
  }

  private function getTableColumns($table_name) {
    if (!array_key_exists($table_name, $this->tablesColumns)) {
      $rows = $this->db->query("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '".Config::DATABASE."' AND `TABLE_NAME`='$table_name';");
      $columns = array();
      while (($r = ($rows->fetch(PDO::FETCH_NUM))) != false) {
        $columns[] = $r[0];
      }
      $this->tablesColumns[$table_name] = array_flip($columns);
    }
    return $this->tablesColumns[$table_name];
  }

  private function calcMinMaxVals() {
    if ($this->minVal === null || $this->maxVal === null) {
      $rows = $this->db->query('SELECT MIN(val), MAX(val) FROM `'.EpivizApiController::VALUES_TABLE.'`;');
      if (($r = ($rows->fetch(PDO::FETCH_NUM))) != false) {
        $this->minVal = 0 + $r[0];
        $this->maxVal = 0 + $r[1];
      }
    }
  }

  private function measurementExists($measurement) {
    $sql = 'SELECT `id` FROM `'.EpivizApiController::COLS_TABLE.'` WHERE `id`=:measurement LIMIT 1;';

    $stmt = $this->db->prepare($sql);
    $stmt->execute(array('measurement' => $measurement));
    return !empty($stmt) && ($stmt->fetch(PDO::FETCH_NUM)) != false;
  }

  /**
   * @param Node $node
   * @param callable $callback
   */
  public static function dfs(Node &$node = null, callable $callback) {
    if ($node === null) { return; }

    $callback($node);

    if (empty($node->children)) { return; }

    foreach ($node->children as $child) {
      EpivizApiController::dfs($child, $callback);
    }
  }

  /**
   * @param array $node_ids
   * @return array
   */
  public function getNodes(array $node_ids = null) {
    if (empty($node_ids)) { return array(); }

    $in_query = implode(',', array_fill(0, count($node_ids), '?'));
    $sql = sprintf($this->nodesQueryFormat, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE, $in_query)
      .$this->nodesOrderBy;
    $stmt = $this->db->prepare($sql);
    $stmt->execute($node_ids);

    $nodes = array();

    // `id`, `label`, `depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineage`
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $id = $r[0];
      $nodes[$id] = new Node(
        $id, // id
        $r[1], // label
        0 + $r[2], // depth
        $r[9], // taxonomy
        $r[3], // parentId
        0 + $r[8], // nchildren
        null,
        $r[7], // partition
        $r[5], // start
        $r[6], // end
        0 + $r[10], // leafIndex
        0 + $r[11], // nleaves
        0 + $r[12], // order
        $r[13]); // lineage
    }

    return $nodes;
  }

  public function getSiblings(array $node_ids = null) {
    if (empty($node_ids)) { return array(); }

    $in_query = implode(',', array_fill(0, count($node_ids), '?'));
    $sql = sprintf($this->siblingsQueryFormat, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE, $in_query)
      .$this->nodesOrderBy;
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array_merge($node_ids, $node_ids));

    $nodes = array();

    // `id`, `label`, `depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineage`
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $id = $r[0];
      $nodes[$id] = new Node(
        $id, // id
        $r[1], // label
        0 + $r[2], // depth
        $r[9], // taxonomy
        $r[3], // parentId
        0 + $r[8], // nchildren
        null,
        $r[7], // partition
        $r[5], // start
        $r[6], // end
        0 + $r[10], // leafIndex
        0 + $r[11], // nleaves
        0 + $r[12], // order
        $r[13]); // lineage
    }

    return $nodes;
  }

  private function extractSelectionNodes(array &$nodes, array &$selection=array()) {
    // Selection
    $selection_node_ids = array_keys($selection);
    $selection_nodes = array();
    foreach ($selection_node_ids as $node_id) {
      // Discard nodes set to LEAVES
      if (idx($selection, $node_id) === SelectionType::LEAVES) {
        unset($selection[$node_id]);
        continue;
      }
      $node = idx($nodes, $node_id);
      if ($node !== null) {
        $selection_nodes[$node_id] = $node;
      }
    }

    uasort($selection_nodes, function(&$n1, &$n2) {
      return $n1->start - $n2->start;
    });

    return $selection_nodes;
  }

  private function buildSelectionIntervals(array &$selection_nodes, $start=null, $end=null) {
    // Discard nodes included in larger ranges of ancestors
    $selection_node_ids = array_keys($selection_nodes);
    $prev_node = null;
    foreach ($selection_node_ids as $i => $node_id) {
      $node = $selection_nodes[$node_id];
      if ($prev_node === null) {
        $prev_node = $node;
        continue;
      }
      if ($prev_node->end >= $node->end) {
        unset($selection_nodes[$node_id]);
        continue;
      }

      $prev_node = $node;
    }

    $cond_selection_nodes = array_filter($selection_nodes, function($node) use ($start, $end) {
      return ($end === null || $node->start < $end) && ($start === null || $node->end > $start);
    });

    return $cond_selection_nodes;
  }

  private function calcSelectionNodeIndexes(array &$selection_nodes, $start=null, $end=null) {
    // Compute updated indexes for selection nodes
    $index_collapse = 0;
    $start_index_collapse = null;
    $selection_nodes_indexes = array();
    foreach ($selection_nodes as $node_id => $node) {
      if ($start === null || $node->end > $start) {
        $selection_nodes_indexes[$node_id] = $node->leafIndex - $index_collapse;
      }
      if (($start === null || $node->end > $start) && $start_index_collapse === null) {
        $start_index_collapse = $index_collapse;
      }
      $index_collapse += $node->nleaves;
      if (idx($selection, $node_id) === SelectionType::NODE) {
        --$index_collapse;
      }
    }

    return array($selection_nodes_indexes, $start_index_collapse);
  }

  public function getRows($start, $end, $partition=null, array $metadata=null, $retrieve_index=true, $retrieve_end=true, $offset_location=false, array $selection=array(), array $order=array()) {
    $location_cols = array_flip(array('index', 'partition', 'start', 'end'));
    $columns = $this->getTableColumns(EpivizApiController::ROWS_TABLE);
    if ($metadata != null) {
      $safe_metadata_cols = array();
      foreach ($metadata as $col) {
        if (array_key_exists($col, $columns) && !array_key_exists($col, $location_cols)) {
          $safe_metadata_cols[] = $col;
        }
      }
      $metadata = $safe_metadata_cols;
    } else {
      $metadata = array();
      foreach ($columns as $col => $_) {
        if (!array_key_exists($col, $location_cols)) {
          $metadata[] = $col;
        }
      }
    }

    $node_ids = array_keys($selection + $order);
    $nodes = $this->getSiblings($node_ids);

    $selection_nodes = $this->extractSelectionNodes($nodes, $selection);
    $cond_selection_nodes = $this->buildSelectionIntervals($selection_nodes, $start, $end);
    list($selection_nodes_indexes, $start_index_collapse) = $this->calcSelectionNodeIndexes($selection_nodes, $start, $end);

    // Build correct select intervals
    $cond = implode(' OR ', array_fill(0, 1+count($cond_selection_nodes),
      sprintf($this->intervalQueryFormat, EpivizApiController::ROWS_TABLE, $partition == null ? '`partition` IS NULL' : '`partition` = ?')));

    $fields = '`index`, `start`';
    $metadata_cols_index = 2;
    if ($retrieve_end) { $fields .= ', `end`'; ++$metadata_cols_index; }

    $params = array();
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $start;
    $last_end = $start;
    foreach ($cond_selection_nodes as $node) {
      $params[] = $node['start'];
      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $last_end;
      $params[] = $node['start'];

      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $node['end'];
      $last_end = $node['end'];
    }
    $params[] = $end;
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $last_end;
    $params[] = $end;

    foreach ($metadata as $col) {
      $fields .= ', `' . $col . '`';
    }

    $ret = new RowCollection($metadata, $offset_location, $retrieve_index, $retrieve_end);

    $sql = sprintf($this->rowsQueryFormat, $fields, EpivizApiController::ROWS_TABLE, $cond);

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $selection_node = null;
    list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
    $selection_node = idx($selection_nodes, $selection_node_id);
    $index_collapse = $start_index_collapse;

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $s = 0 + $r[1];

      while ($selection_node !== null && $s >= $selection_node->end) {
        if (idx($selection, $selection_node_id) === SelectionType::NODE) {
          $ret->add($selection_node_index, $selection_node->start, $selection_node->end, get_object_vars($selection_node));
        }

        list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
        $selection_node = idx($selection_nodes, $selection_node_id);
        $index_collapse = $selection_node->leafIndex - $selection_node_index;
      }

      $r[0] = 0 + $r[0] - $index_collapse;
      $ret->addRow($r);

      if ($ret->count() == 1) { $ret->globalStartIndex -= $index_collapse; }
    }

    while (list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes)) {
      $selection_node = $selection_nodes[$selection_node_id];
      if ($selection_node->start >= $end) { break; }

      $ret->add($selection_node_index, $selection_node->start, $selection_node->end, get_object_vars($selection_node));
    }

    // Apply ordering
    if (!empty($order)) {
      $parent_ids = array_flip(array_map(function($node_id) use ($nodes) { return $nodes[$node_id]->parentId; }, array_keys($order)));
      $order_nodes = array_filter($nodes, function($node) use ($parent_ids) { return array_key_exists($node->parentId, $parent_ids); });
      array_walk($order_nodes, function(&$val) use ($order) { $val->order = idx($order, $val->id, $val->order); });

      // AICI: Copy from here
      $interval_limits = array();
      foreach ($order_nodes as &$node) {
        $interval_limits[] = array('val' => $node['leafIndex'], 'depth' => $node['depth'], 'isStart' => true, 'node' => &$node);
        $interval_limits[] = array('val' => $node['leafIndex'] + $node['nleaves'], 'depth' => $node['depth'], 'isStart' => false, 'node' => &$node);
      }
      usort($interval_limits, function($e1, $e2) {
        $ret = $e1['val'] - $e2['val'];
        if ($ret == 0) {
          if ($e1['isStart'] xor $e2['isStart']) {
            $ret = ($e1['isStart']) ? 1 : -1;
          } else {
            $ret = $e1['depth'] - $e2['depth'];
          }
        }
        return $ret;
      });
      $n = count($interval_limits);
      $node_stack = array();
      $intervals = array();
      $last_interval = null;
      $interval_root = array('start' => 0, 'end' => null, 'node' => null, 'indices' => array(), 'children' => array());
      $node_stack[] = &$interval_root;
      for ($i = 0; $i < $n; ++$i) {
        $interval = null;
        $val = $interval_limits[$i]['val'];
        //$node = end($node_stack);
        $interval_node = &$node_stack[count($node_stack) - 1];
        //if ($node === false) { $node = null; }
        if (count($intervals) == 0) {
          $interval = array('start' => 0, 'end' => $val, 'node' => $interval_node['node'], 'indices' => array(), 'children' => array());
        } else {
          if ($val > $last_interval['end']) {
            $interval = array('start' => $last_interval['end'], 'end' => $val, 'node' => $interval_node['node'], 'indices' => array(), 'children' => array());
          }
        }
        if ($interval !== null) {
          $intervals[] = $interval;
          $last_interval = $interval;
          $children = &$interval_node['children'];
          $children[] = $interval;
        }
        if ($interval_limits[$i]['isStart']) {
          //$node_stack[] = &$interval_limits[$i]['node'];
          //$node_stack[] =
        } else {
          array_pop($node_stack);
        }

      }
      $intervals[] = array('start' => $last_interval['end'], 'end' => null, 'node' => null, 'indices' => array());

      $n = count($values['index']);
      $last_start = null;
      $last_end = null;
      $interval = null;
      for ($i = 0; $i < $n; ++$i) {
        $start = $values['start'][$i];
        $end = $values['end'][$i];
        if ($offset_location && $i > 0) {
          $start += $last_start;
          $last_start = $start;
          $end += $last_end;
          $last_end = $end;
        }
        if ($interval === null || !between($start, $interval['start'], $interval['end'])) {
          $interval_index = binary_search(array('start' => $start, 'end' => $end), $intervals, function(&$needle, &$interval) {
            if (between($needle['start'], $interval['start'], $interval['end'])) { return 0; }
            if ($needle['start'] < $interval['start']) { return -1; }
            return 1;
          });

          $interval = $intervals[$interval_index];
        }
        $interval['indices'][] = $i;
      }

      // Now order intervals
    }
    /*print_r($selection_nodes);
    print_r($selection_nodes_indexes);
    print_r($start_index_collapse);*/



    // Get correct order of nodes in order map
    /*$parent_ids = array();
    foreach ($order as $node_id) {
      $parent_ids[] = $nodes[$node_id]['parentId'];
    }
    $parents = $this->getHierarchies(1, $parent_ids, $order);
    uasort($parents, function(&$n1, &$n2) {
      return $n1['start'] - $n2['start'];
    });
    $ordered_nodes = array();
    foreach ($parents as $parent) {
      foreach ($parent['children'] as $node) {
        $ordered_nodes[$node['id']] = $node;
      }
    }*/

    $data = array(
      'values' => $values,
      'globalStartIndex' => $min_index,
      'useOffset' => $offset_location
    );

    return $data;
  }

  public function getRowsOld($start, $end, $partition=null, array $metadata=null, $retrieve_index=true, $retrieve_end=true, $offset_location=false, array $selection=null, array $order=null) {
    // TODO: Here is where we should also send the selection types and decide what values we're showing based on that
    // TODO: Also, order comes into play here too
    if ($selection === null) { $selection = array(); }
    if ($order === null) { $order = array(); }

    $node_ids = array_keys($selection + $order);
    $nodes = $this->getSiblings($node_ids);

    // Selection

    $selection_node_ids = array_keys($selection);
    $selection_nodes = array();
    foreach ($selection_node_ids as $node_id) {
      // Discard nodes set to LEAVES
      if (idx($selection, $node_id) === SelectionType::LEAVES) {
        unset($selection[$node_id]);
        continue;
      }
      $node = idx($nodes, $node_id);
      if ($node !== null) {
        $selection_nodes[$node_id] = $node;
      }
    }

    // Discard nodes included in larger ranges of ancestors
    uasort($selection_nodes, function(&$n1, &$n2) {
      return $n1['start'] - $n2['start'];
    });
    $selection_node_ids = array_keys($selection_nodes);
    $prev_node = null;
    foreach ($selection_node_ids as $i => $node_id) {
      $node = $selection_nodes[$node_id];
      if ($prev_node === null) {
        $prev_node = $node;
        continue;
      }
      if ($prev_node['end'] >= $node['end']) {
        unset($selection_nodes[$node_id]);
        continue;
      }

      $prev_node = $node;
    }

    $cond_selection_nodes = array_filter($selection_nodes, function($node) use ($start, $end) {
      return $node['start'] < $end && $node['end'] > $start;
    });

    // Build correct select intervals
    $cond = implode(' OR ', array_fill(0, 1+count($cond_selection_nodes),
      sprintf($this->intervalQueryFormat, EpivizApiController::ROWS_TABLE, $partition == null ? '`partition` IS NULL' : '`partition` = ?')));

    // Compute updated indexes for selection nodes
    $index_collapse = 0;
    $start_index_collapse = null;
    $selection_nodes_indexes = array();
    foreach ($selection_nodes as $node_id => $node) {
      if ($node['end'] > $start) {
        $selection_nodes_indexes[$node_id] = $node['leafIndex'] - $index_collapse;
      }
      if ($node['end'] > $start && $start_index_collapse === null) {
        $start_index_collapse = $index_collapse;
      }
      $index_collapse += $node['nleaves'];
      if (idx($selection, $node_id) === SelectionType::NODE) {
        --$index_collapse;
      }
    }

    $fields = '`index`, `start`';
    $metadata_cols_index = 2;
    if ($retrieve_end) { $fields .= ', `end`'; ++$metadata_cols_index; }

    $params = array();
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $start;
    $last_end = $start;
    foreach ($cond_selection_nodes as $node) {
      $params[] = $node['start'];
      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $last_end;
      $params[] = $node['start'];

      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $node['end'];
      $last_end = $node['end'];
    }
    $params[] = $end;
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $last_end;
    $params[] = $end;

    $values = array(
      'index' => $retrieve_index ? array() : null,
      'start' => array(),
      'end' => $retrieve_end ? array() : null,
    );

    // Compress the sent data so that the message is sent a faster over the network
    $min_index = null;
    $last_start = null;
    $last_end = null;

    $location_cols = array(
      'index' => true,
      'partition' => true,
      'start' => true,
      'end' => true
    );
    $columns = $this->getTableColumns(EpivizApiController::ROWS_TABLE);
    if ($metadata != null) {
      $safe_metadata_cols = array();
      foreach ($metadata as $col) {
        if (array_key_exists($col, $columns) && !array_key_exists($col, $location_cols)) {
          $safe_metadata_cols[] = $col;
        }
      }
      $metadata = $safe_metadata_cols;
    } else {
      $metadata = array();
      foreach ($columns as $col => $_) {
        if (!array_key_exists($col, $location_cols)) {
          $metadata[] = $col;
        }
      }
    }

    foreach ($metadata as $col) {
      $fields .= ', `' . $col . '`';
    }

    if (!empty($metadata)) {
      $values['metadata'] = array();
      foreach ($metadata as $col) {
        $values['metadata'][$col] = array();
      }
    }

    $sql = sprintf($this->rowsQueryFormat,
      $fields,
      EpivizApiController::ROWS_TABLE,
      $cond
    );

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $selection_node = null;
    list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
    $selection_node = idx($selection_nodes, $selection_node_id);
    $index_collapse = $start_index_collapse;

    $last_start = 0;
    $last_end = 0;

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $start = 0 + $r[1];
      $end = $retrieve_end ? 0 + $r[2] : null;

      while ($selection_node !== null && $start >= $selection_node['end']) {
        if (idx($selection, $selection_node_id) === SelectionType::NODE) {
          $selection_node_start = $selection_node['start'];
          $selection_node_end = $selection_node['end'];
          if ($offset_location) {
            $selection_node_start -= $last_start;
            $last_start = $selection_node['start'];
            $selection_node_end -= $last_end;
            $last_end = $selection_node['end'];
          }
          $values['start'][] = $selection_node_start;
          if ($retrieve_end) {
            $values['end'][] = $selection_node_end;
          }

          $values['index'][] = $selection_node_index;
          if (!empty($metadata)) {
            foreach ($metadata as $col) {
              $values['metadata'][$col][] = idx($selection_node, $col);
            }
          }
        }

        list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
        $selection_node = idx($selection_nodes, $selection_node_id);
        $index_collapse = $selection_node['leafIndex'] - $selection_node_index;
      }

      if ($min_index === null) { $min_index = 0 + $r[0] - $index_collapse; }
      if ($retrieve_index) { $values['index'][] = 0 + $r[0] - $index_collapse; }

      if ($offset_location) {
        $start -= $last_start;
        if ($retrieve_end) { $end -= $last_end; }

        $last_start = 0 + $r[1];
        if ($retrieve_end) { $last_end = 0 + $r[2]; }
      }

      $values['start'][] = $start;
      if ($retrieve_end) { $values['end'][] = $end; }
      if (!empty($metadata)) {
        $col_index = $metadata_cols_index;
        foreach ($metadata as $col) {
          $values['metadata'][$col][] = $r[$col_index++];
        }
      }
    }

    while (list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes)) {
      $selection_node = $selection_nodes[$selection_node_id];
      if ($selection_node['start'] >= $end) { break; }

      $selection_node_start = $selection_node['start'];
      $selection_node_end = $selection_node['end'];
      if ($offset_location) {
        $selection_node_start -= $last_start;
        $last_start = $selection_node['start'];
        $selection_node_end -= $last_end;
        $last_end = $selection_node['end'];
      }
      $values['start'][] = $selection_node_start;
      if ($retrieve_end) {
        $values['end'][] = $selection_node_end;
      }

      $values['index'][] = $selection_node_index;
      if (!empty($metadata)) {
        foreach ($metadata as $col) {
          $values['metadata'][$col][] = idx($selection_node, $col);
        }
      }
    }

    // Apply ordering
    if (!empty($order)) {
      $parent_ids = array_flip(array_map(function($node_id) use ($nodes) { return $nodes[$node_id]['parentId']; }, array_keys($order)));
      $order_nodes = array_filter($nodes, function($node) use ($parent_ids) { return array_key_exists($node['parentId'], $parent_ids); });

      $interval_limits = array();
      foreach ($order_nodes as &$node) {
        $interval_limits[] = array('val' => $node['leafIndex'], 'depth' => $node['depth'], 'isStart' => true, 'node' => &$node);
        $interval_limits[] = array('val' => $node['leafIndex'] + $node['nleaves'], 'depth' => $node['depth'], 'isStart' => false, 'node' => &$node);
      }
      usort($interval_limits, function($e1, $e2) {
        $ret = $e1['val'] - $e2['val'];
        if ($ret == 0) {
          if ($e1['isStart'] xor $e2['isStart']) {
            $ret = ($e1['isStart']) ? 1 : -1;
          } else {
            $ret = $e1['depth'] - $e2['depth'];
          }
        }
        return $ret;
      });
      $n = count($interval_limits);
      $node_stack = array();
      $intervals = array();
      $last_interval = null;
      $interval_root = array('start' => 0, 'end' => null, 'node' => null, 'indices' => array(), 'children' => array());
      $node_stack[] = &$interval_root;
      for ($i = 0; $i < $n; ++$i) {
        $interval = null;
        $val = $interval_limits[$i]['val'];
        //$node = end($node_stack);
        $interval_node = &$node_stack[count($node_stack) - 1];
        //if ($node === false) { $node = null; }
        if (count($intervals) == 0) {
          $interval = array('start' => 0, 'end' => $val, 'node' => $interval_node['node'], 'indices' => array(), 'children' => array());
        } else {
          if ($val > $last_interval['end']) {
            $interval = array('start' => $last_interval['end'], 'end' => $val, 'node' => $interval_node['node'], 'indices' => array(), 'children' => array());
          }
        }
        if ($interval !== null) {
          $intervals[] = $interval;
          $last_interval = $interval;
          $children = &$interval_node['children'];
          $children[] = $interval;
        }
        if ($interval_limits[$i]['isStart']) {
          //$node_stack[] = &$interval_limits[$i]['node'];
          //$node_stack[] =
        } else {
          array_pop($node_stack);
        }

      }
      $intervals[] = array('start' => $last_interval['end'], 'end' => null, 'node' => null, 'indices' => array());

      $n = count($values['index']);
      $last_start = null;
      $last_end = null;
      $interval = null;
      for ($i = 0; $i < $n; ++$i) {
        $start = $values['start'][$i];
        $end = $values['end'][$i];
        if ($offset_location && $i > 0) {
          $start += $last_start;
          $last_start = $start;
          $end += $last_end;
          $last_end = $end;
        }
        if ($interval === null || !between($start, $interval['start'], $interval['end'])) {
          $interval_index = binary_search(array('start' => $start, 'end' => $end), $intervals, function(&$needle, &$interval) {
            if (between($needle['start'], $interval['start'], $interval['end'])) { return 0; }
            if ($needle['start'] < $interval['start']) { return -1; }
            return 1;
          });

          $interval = $intervals[$interval_index];
        }
        $interval['indices'][] = $i;
      }

      // Now order intervals
    }
    /*print_r($selection_nodes);
    print_r($selection_nodes_indexes);
    print_r($start_index_collapse);*/



    // Get correct order of nodes in order map
    /*$parent_ids = array();
    foreach ($order as $node_id) {
      $parent_ids[] = $nodes[$node_id]['parentId'];
    }
    $parents = $this->getHierarchies(1, $parent_ids, $order);
    uasort($parents, function(&$n1, &$n2) {
      return $n1['start'] - $n2['start'];
    });
    $ordered_nodes = array();
    foreach ($parents as $parent) {
      foreach ($parent['children'] as $node) {
        $ordered_nodes[$node['id']] = $node;
      }
    }*/

    $data = array(
      'values' => $values,
      'globalStartIndex' => $min_index,
      'useOffset' => $offset_location
    );

    return $data;
  }

  public function getValues($measurement, $start, $end, $partition) {
    // TODO: Here is where we should also send the selection types and decide what values we're showing based on that
    // TODO: Also, order comes into play here too
    if (!$this->measurementExists($measurement)) {
      return array(
        'globalStartIndex' => null,
        'values' => null
      );
    }

    $params = array(
      'measurement' => $measurement,
      'start1' => $start,
      'start2' => $start,
      'end1' => $end,
      'end2' => $end);
    if ($partition != null) {
      $params['part1'] = $partition;
      $params['part2'] = $partition;
    }

    $sql = sprintf($this->valsQueryFormat,
      EpivizApiController::ROWS_TABLE,
      EpivizApiController::VALUES_TABLE,
      EpivizApiController::COLS_TABLE,
      $partition == null ? '`partition` IS NULL' : '`partition` = :part1',
      $partition == null ? '`partition` IS NULL' : '`partition` = :part2'
    );

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $data = array(
      'globalStartIndex' => null,
      'values' => array()
    );

    $min_index = null;
    $last_index = null;
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      if ($min_index === null) { $min_index = 0 + $r[1]; }
      $data['values'][] = $r[0] == null ? 0 : round(0 + $r[0], 3);
    }
    $data['globalStartIndex'] = $min_index;

    return $data;
  }

  public function getMeasurements($max_count, $annotation) {
    $fields = '`id`, `label`';
    $annotation_index = 2;

    $general_cols = array(
      'id' => true,
      'label' => true
    );
    $columns = $this->getTableColumns(EpivizApiController::COLS_TABLE);
    if ($annotation != null) {
      $safe_annotation_cols = array();
      foreach ($annotation as $col) {
        if (array_key_exists($col, $columns) && !array_key_exists($col, $general_cols)) {
          $safe_annotation_cols[] = $col;
        }
      }
      $annotation = $safe_annotation_cols;
    } else {
      $annotation = array();
      foreach ($columns as $col => $_) {
        if (!array_key_exists($col, $general_cols)) {
          $annotation[] = $col;
        }
      }
    }

    foreach ($annotation as $col) {
      $fields .= ', `' . $col . '`';
    }

    $sql = sprintf($this->colsQueryFormat,
      $fields,
      EpivizApiController::COLS_TABLE,
      $max_count > 0 ? 'LIMIT '.$max_count : ''
    );

    $stmt = $this->db->prepare($sql);
    $stmt->execute();

    $this->calcMinMaxVals();
    $columns = $this->getTableColumns(EpivizApiController::ROWS_TABLE);
    $location_cols = array(
      'index' => true,
      'partition' => true,
      'start' => true,
      'end' => true
    );
    $metadata_cols = array();
    foreach ($columns as $col => $_) {
      if (!array_key_exists($col, $location_cols)) {
        $metadata_cols[] = $col;
      }
    }

    $result = array(
      'id' => array(),
      'name' => array(),
      'type' => 'feature',
      'datasourceId' => Config::DATASOURCE,
      'datasourceGroup' => Config::DATASOURCE,
      'defaultChartType' => '',
      'annotation' => array(),
      'minValue' => $this->minVal,
      'maxValue' => $this->maxVal,
      'metadata' => $metadata_cols
    );

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $result['id'][] = $r[0];
      $result['name'][] = $r[1];

      $anno = array();
      $n = count($annotation);
      for ($i = 0; $i < $n; ++$i) {
        $anno[$annotation[$i]] = $r[$annotation_index + $i];
      }

      $result['annotation'][] = $anno;
    }

    return $result;
  }

  public function getHierarchy($depth, $node_id=null, $selection=null, $order=null) {
    if ($node_id === null) { $node_id = '0-0'; }
    $node_depth = hexdec(explode('-', $node_id)[0]);
    $max_depth = $node_depth + $depth;
    $sql = sprintf($this->hierarchyQueryFormat, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE).$this->nodesOrderBy;
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array(
      '%'.$node_id.'%',
      $max_depth
    ));

    $root = null;
    $node_map = array();
    // `id`, `label`, `depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `taxonomy`, `leafIndex`, `nleaves`, `order`
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node = array(
        'id' => $r[0],
        'name' => $r[1],
        'label' => $r[1],
        'globalDepth' => 0 + $r[2],
        'depth' => 0 + $r[2],
        'taxonomy' => $r[9],
        'parentId' => $r[3],
        'nchildren' => 0 + $r[8],
        'size' => 1,
        'selectionType' => idx($selection, $r[0], SelectionType::LEAVES),
        'start' => 0 + $r[5],
        'end' => 0 + $r[6],
        'leafIndex' => 0 + $r[10],
        'nleaves' => 0 + $r[11],
        'order' => 0 + $r[12],
        'children' => array()
      );

      if ($node['id'] == $node_id) {
        $root = $node;
        $node_map[$node_id] = &$root;
      } else {
        $parent_id = $r[3];
        $siblings = &$node_map[$parent_id]['children'];
        $node_order = idx($order, $node['id'], $node['order']);
        $node['order'] = $node_order;
        $siblings[] = $node;
        $node_map[$node['id']] = &$siblings[count($siblings)-1];
      }
    }

    EpivizApiController::dfs($root, function(&$node) {
      if (!array_key_exists('children', $node)) { return; }
      $children = &$node['children'];
      usort($children, function(&$c1, &$c2) {
        return $c1['order'] - $c2['order'];
      });
    });

    return $root;
  }

  public function getHierarchies($depth, $node_ids, $order=null) {
    $max_depths = array_map(function($node_id) { return hexdec(explode('-', $node_id)[0]); }, $node_ids);
    foreach ($max_depths as &$max_depth) { $max_depth += $depth; }
    $sqls = array_fill(0, count($node_ids), sprintf($this->hierarchyQueryFormat, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE));
    $sql = implode(' UNION ', $sqls).$this->nodesOrderBy;
    $params = array();
    foreach ($node_ids as $i => $node_id) {
      $params[] = '%'.$node_id.'%';
      $params[] = $max_depths[$i];
    }
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $node_map = array();
    // `id`, `label`, `depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `taxonomy`, `leafIndex`, `nleaves`, `order`
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node_id = $r[0];
      $node = array(
        'id' => $node_id,
        'name' => $r[1],
        'label' => $r[1],
        'globalDepth' => 0 + $r[2],
        'depth' => 0 + $r[2],
        'taxonomy' => $r[9],
        'parentId' => $r[3],
        'nchildren' => 0 + $r[8],
        'size' => 1,
        'selectionType' => idx($selection, $r[0], SelectionType::LEAVES),
        'start' => 0 + $r[5],
        'end' => 0 + $r[6],
        'leafIndex' => 0 + $r[10],
        'nleaves' => 0 + $r[11],
        'order' => 0 + $r[12],
        'children' => array()
      );
      $node_map[$node_id] = $node;

      $parent_id = $node['parentId'];
      if (array_key_exists($parent_id, $node_map)) {
        $siblings = &$node_map[$parent_id]['children'];
        $new_order = idx($order, $node_id, $node['order']);
        $node_order = &$node_map[$node_id]['order'];
        $node_order = $new_order;
        $siblings[] = &$node_map[$node_id];
      }
    }

    $ret = array();
    foreach ($node_ids as $node_id) {
      $node = &$node_map[$node_id];
      EpivizApiController::dfs($node, function(&$node) {
        if (!array_key_exists('children', $node)) { return; }
        $children = &$node['children'];
        usort($children, function(&$c1, &$c2) {
          return $c1['order'] - $c2['order'];
        });
      });
      $ret[$node_id] = $node;
    }

    return $ret;
  }
}