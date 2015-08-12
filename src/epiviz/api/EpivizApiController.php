<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:56 PM
 */

namespace epiviz\api;

use epiviz\models\DatasourceTable;
use epiviz\models\RowCollection;
use epiviz\models\ValueCollection;
use epiviz\utils\OrderedIntervalTree;
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

  const TEMP_ROWS = 'temp_rows';
  const TEMP_VALS = 'temp_vals';
  const TEMP_COLS = 'temp_cols';
  const TEMP_HIERARCHY = 'temp_hierarchy';

  private $levelsQueryFormat;
  private $intervalQueryFormat;
  private $rowsQueryFormat;
  private $valsQueryFormat;
  private $colsQueryFormat;
  private $partitionsQueryFormat;
  private $hierarchyQueryFormat;
  private $nodesQueryFormat;
  private $nodesOrderBy;
  private $siblingsQueryFormat;
  private $parentIdIn;
  private $idIn;
  private $depthIn;
  private $db;
  private $tablesColumns = array();
  private $minVal = null;
  private $maxVal = null;

  /**
   * @var ValueAggregatorFactory
   */
  private $aggregatorFactory;

  /**
   * @param ValueAggregatorFactory $aggregator_factory
   */
  public function __construct(ValueAggregatorFactory $aggregator_factory) {
    $this->aggregatorFactory = $aggregator_factory;

    // TODO: Update these queries to the ones actually used in getRows and getValues

    $this->levelsQueryFormat = 'SELECT `depth`, `label` FROM %1$s ORDER BY `depth` ';

    $this->intervalQueryFormat = '(`index` BETWEEN '
      .'(SELECT MIN(`index`) FROM %1$s WHERE %2$s AND `end` > ? AND `start` < ?) AND '
      .'(SELECT MAX(`index`) FROM %1$s WHERE %2$s AND `end` > ? AND `start` < ?)) ';

    $this->rowsQueryFormat = 'SELECT %1$s FROM %2$s WHERE %3$s ORDER BY `index` ASC ';

    $this->valsQueryFormat =
      'SELECT `val`, `%1$s`.`index`, `%1$s`.`start`, `%1$s`.`end` FROM `%1$s` LEFT OUTER JOIN '
      .'(SELECT `val`, `row`, `col` FROM `%2$s` JOIN `%3$s` ON `col` = `index` WHERE `%3$s`.`id` = ?) vals '
      .'ON vals.`row` = `%1$s`.`index` '
      .'WHERE %4$s ORDER BY `%1$s`.`index` ASC ';

    $this->colsQueryFormat =
      'SELECT %1$s FROM %2$s ORDER BY `id` ASC %3$s ';

    $this->partitionsQueryFormat = 'SELECT `partition`, MIN(`start`), MAX(`end`) FROM %1$s GROUP BY `partition` ORDER BY `partition` ASC';

    $this->hierarchyQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineageLabel` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE `lineage` LIKE ? AND `%1$s`.`depth` <= ? ';

    $this->nodesQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineageLabel` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE `id` IN (%3$s) ';

    $this->siblingsQueryFormat =
      'SELECT `id`, `%1$s`.`label`, `%1$s`.`depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `%2$s`.`label` AS `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineageLabel` '
      .'FROM `%1$s` JOIN `%2$s` ON `%1$s`.`depth` = `%2$s`.`depth` WHERE ';

    $this->parentIdIn = '`parentId` IN (SELECT `parentId` FROM `%1$s` WHERE `id` IN (%2$s)) ';
    $this->idIn = '`id` IN (%1$s) ';
    $this->depthIn = '`%1$s`.`depth` IN (%2$s) ';

    $this->nodesOrderBy = 'ORDER BY `depth`, `partition`, `start`, `end` ';

    // TODO: After upgrading to PHP 5.4.2, replace the two lines below with the commented line
    // $this->db = (new EpivizDatabase())->db();
    $epiviz_db = new EpivizDatabase();
    $this->db = $epiviz_db->db();
  }

  /**
   * Retrieves the column names of the given table
   * @param string $table_name
   * @return array An associative array storing column => index
   */
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

  /**
   * @param string $measurement
   * @return bool
   */
  private function measurementExists($measurement) {
    $sql = 'SELECT `id` FROM `'.EpivizApiController::COLS_TABLE.'` WHERE `id`=:measurement LIMIT 1;';

    $stmt = $this->db->prepare($sql);
    $stmt->execute(array('measurement' => $measurement));
    return !empty($stmt) && ($stmt->fetch(PDO::FETCH_NUM)) != false;
  }

  /**
   * @param array $r
   * @return Node
   */
  private static function createNodeFromDbRecord(array $r) {
    // 0,    1,       2,       3,          4,         5,       6,     7,           8,           9,          10,          11,        12,      13,
    // `id`, `label`, `depth`, `parentId`, `lineage`, `start`, `end`, `partition`, `nchildren`, `taxonomy`, `leafIndex`, `nleaves`, `order`, `lineageLabel`
    $id = $r[0];
    return new Node(
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
      $r[4], // lineage
      $r[13]); // lineageLabel
  }

  /**
   * TODO: After upgrading to PHP 5.4.2 or later, uncomment the callable attribute
   * @param Node $node
   * @param callable $callback
   */
  public static function dfs(Node &$node = null, /* callable */ $callback) {
    if ($node === null) { return; }

    $callback($node);

    if (empty($node->children)) { return; }

    foreach ($node->children as $child) {
      EpivizApiController::dfs($child, $callback);
    }
  }

  /**
   * @return array
   */
  public function getAggregatingFunctions() {
    return array_keys($this->aggregatorFactory->values());
  }

  /**
   * Gets the nodes corresponding to the given ids
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

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node = EpivizApiController::createNodeFromDbRecord($r);
      $nodes[$node->id] = $node;
    }

    return $nodes;
  }

  /**
   * Gets the nodes corresponding to the given ids, and all their siblings
   * @param array $node_ids
   * @param array $selected_levels
   * @return array
   */
  public function getSiblings(array $node_ids = null, array $selected_levels=null) {
    if (empty($node_ids) && empty($selected_levels)) { return array(); }

    $format = $this->siblingsQueryFormat;
    $params = array();
    $conditions = array();
    if (!empty($node_ids)) {
      $in_query = implode(',', array_fill(0, count($node_ids), '?'));
      $params = array_merge($node_ids, $node_ids);
      $conditions[] = sprintf($this->parentIdIn, EpivizApiController::HIERARCHY_TABLE, $in_query);
      $conditions[] = sprintf($this->idIn, $in_query);
    }

    $depths = array();
    if (!empty($selected_levels)) {
      foreach ($selected_levels as $level => $selection) {
        if ($selection != SelectionType::LEAVES) {
          $depths[] = $level;
        }
      }
    }

    if (!empty($depths)) {
      $depth_in = implode(',', array_fill(0, count($depths), '?'));
      $conditions[] = sprintf($this->depthIn, EpivizApiController::HIERARCHY_TABLE, $depth_in);
      $params = array_merge($params, $depths);
    }

    $sql = sprintf($format . implode(' OR ', $conditions), EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE)
      .$this->nodesOrderBy;

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $nodes = array();

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node = EpivizApiController::createNodeFromDbRecord($r);
      $nodes[$node->id] = $node;
    }

    return $nodes;
  }

  private function extractSelectionNodes(array &$nodes, array &$selection=array(), array &$selected_levels=null) {
    // Selection
    $selection_nodes = array();

    foreach ($nodes as $node) {
      $node_selection = idx($selected_levels, $node->depth, idx($selection, $node->id, SelectionType::LEAVES));
      if ($node_selection == SelectionType::LEAVES) {
        // Discard nodes set to LEAVES
        unset($selection[$node->id]);
        unset($selected_levels[$node->depth]);
        continue;
      }

      $node->selectionType = $node_selection;
      $selection_nodes[$node->id] = $node;
    }

    // Discard nodes included in larger ranges of ancestors
    uasort($selection_nodes, function(Node $n1, Node $n2) {
      $ret = $n1->start - $n2->start;
      if ($ret != 0) { return $ret; }
      return $n1->depth - $n2->depth;
    });

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

    return $selection_nodes;
  }

  private function filterOutOfRangeSelectionNodes(array &$selection_nodes, $start=null, $end=null) {
    return array_filter($selection_nodes, function(Node $node) use ($start, $end) {
      return ($end === null || $node->start < $end) && ($start === null || $node->end > $start);
    });
  }

  private function calcSelectionNodeIndexes(array &$selection_nodes, array &$selection, array $selected_levels=null, $start=null, $end=null) {
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

      $node_selection = idx($selected_levels, $node->depth, idx($selection, $node_id, SelectionType::LEAVES));
      if ($node_selection === SelectionType::NODE) {
        --$index_collapse;
      }
    }

    return array($selection_nodes_indexes, $start_index_collapse);
  }

  /**
   * Gets an array of depth => hierarchy level name
   * @return array
   */
  public function getLevels() {
    $sql = sprintf($this->levelsQueryFormat, EpivizApiController::LEVELS_TABLE);

    $stmt = $this->db->prepare($sql);
    $stmt->execute();

    $ret = array();
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $ret[$r[0]] = $r[1];
    }

    return $ret;
  }

  /**
   * @param int $start
   * @param int$end
   * @param string $partition
   * @param array $metadata
   * @param bool $retrieve_index
   * @param bool $retrieve_end
   * @param bool $offset_location
   * @param array $selection
   * @param array $order
   * @param array $selected_levels
   * @return RowCollection
   */
  public function getRows($start, $end, $partition=null, array $metadata=null, $retrieve_index=true, $retrieve_end=true,
                          $offset_location=false, array $selection=null, array $order=null, array $selected_levels=null) {
    if ($selection === null) { $selection = array(); }
    if ($order === null) { $order = array(); }
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
    $nodes = $this->getSiblings($node_ids, $selected_levels);

    $selection_nodes = $this->extractSelectionNodes($nodes, $selection, $selected_levels);
    $in_range_selection_nodes = $this->filterOutOfRangeSelectionNodes($selection_nodes, $start, $end);

    list($selection_nodes_indexes, $start_index_collapse) = $this->calcSelectionNodeIndexes($selection_nodes, $selection, $selected_levels, $start, $end);

    // Build correct select intervals
    $cond = implode(' OR ', array_fill(0, 1+count($in_range_selection_nodes),
      sprintf($this->intervalQueryFormat, EpivizApiController::ROWS_TABLE, $partition == null ? '`partition` IS NULL' : '`partition` = ?')));

    $params = array();
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $start;
    $last_end = $start;
    foreach ($in_range_selection_nodes as $node) {
      $params[] = $node->start;
      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $last_end;
      $params[] = $node->start;

      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $node->end;
      $last_end = $node->end;
    }
    $params[] = $end;
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $last_end;
    $params[] = $end;

    $fields = '`%1$s`.`index`, `%1$s`.`start`';
    $metadata_cols_index = 2;
    if ($retrieve_end) { $fields .= ', `%1$s`.`end`'; ++$metadata_cols_index; }

    foreach ($metadata as $col) {
      $fields .= ', `%1$s`.`' . $col . '`';
    }

    $fields .= ', `%2$s`.`lineagelabel`, `%2$s`.`lineage`, `%2$s`.`depth`';
    $fields = sprintf($fields, EpivizApiController::TEMP_ROWS, EpivizApiController::HIERARCHY_TABLE);

    $ret = new RowCollection($metadata, $this->getLevels(), $offset_location, $retrieve_index, $retrieve_end);

    $db = $this->db;
    $db->beginTransaction();

    $db->query(sprintf('DROP TABLE IF EXISTS `%1$s`', EpivizApiController::TEMP_ROWS));
    $db->prepare(sprintf('CREATE TEMPORARY TABLE `%1$s` ENGINE=MEMORY AS (SELECT * FROM `%2$s` WHERE %3$s ORDER BY `index` ASC)',
      EpivizApiController::TEMP_ROWS, EpivizApiController::ROWS_TABLE, $cond))->execute($params);

    $db->commit();

    // TODO: Do not join with hierarchy table, but also make a temp table
    $sql = sprintf('SELECT %1$s FROM `%2$s` LEFT JOIN `%3$s` ON `%2$s`.`id` = `%3$s`.`id` ', $fields, EpivizApiController::TEMP_ROWS, EpivizApiController::HIERARCHY_TABLE);

    $stmt = $this->db->prepare($sql);
    $stmt->execute();

    list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
    $selection_node = idx($selection_nodes, $selection_node_id);
    $index_collapse = $start_index_collapse;

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $s = 0 + $r[1];

      while ($selection_node !== null && $s >= $selection_node->end) {
        if ($selection_node->selectionType === SelectionType::NODE) {
          $ret->add($selection_node_index, $selection_node->start, $selection_node->end, get_object_vars($selection_node), explode(',', $selection_node->lineageLabel()), $selection_node->lineage());
        }

        $index_collapse = 0 + $r[0] - $selection_node_index - 1;

        list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
        $selection_node = idx($selection_nodes, $selection_node_id);

        if ($selection_node !== null) {
          $index_collapse = $selection_node->leafIndex - $selection_node_index;
        }
      }

      $r[0] = 0 + $r[0] - $index_collapse;
      $ret->addDbRecord($r);
    }

    if ($selection_node !== null && $selection_node->selectionType === SelectionType::NODE &&
        $selection_node->end > $start && $selection_node->start < $end) {
      $ret->add($selection_node_index, $selection_node->start, $selection_node->end, get_object_vars($selection_node), explode(',', $selection_node->lineageLabel()), $selection_node->lineage());
    }

    while (list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes)) {
      $selection_node = $selection_nodes[$selection_node_id];
      if ($selection_node->start >= $end) { break; }

      if ($selection_node->selectionType === SelectionType::NODE) {
        $ret->add($selection_node_index, $selection_node->start, $selection_node->end, get_object_vars($selection_node), explode(',', $selection_node->lineageLabel()), $selection_node->lineage());
      }
    }

    // Apply ordering
    if (!empty($order)) {
      $parent_ids = array_flip(array_map(function($node_id) use ($nodes) { return $nodes[$node_id]->parentId; }, array_keys($order)));
      $order_nodes = array_filter($nodes, function(Node $node) use ($parent_ids) { return array_key_exists($node->parentId, $parent_ids); });
      array_walk($order_nodes, function(Node &$node) use ($order) { $node->order = idx($order, $node->id, $node->order); });

      $ordered_interval_tree = new OrderedIntervalTree($order_nodes);
      $ret = $ret->reorder($ordered_interval_tree->orderIntervals($ret));
    }

    return $ret;
  }

  /**
   * @param string $measurement
   * @param int $start
   * @param int $end
   * @param string|null $partition
   * @param array|null $selection
   * @param array|null $order
   * @param string|null $aggregation_function
   * @param array|null $selected_levels
   * @return ValueCollection
   */
  public function getValues($measurement, $start, $end, $partition=null, array $selection=null, array $order=null,
                            $aggregation_function=null, array $selected_levels=null) {
    if ($selection === null) { $selection = array(); }
    if ($order === null) { $order = array(); }

    if ($aggregation_function == null) { $aggregation_function = 'average'; }
    $agg_func = $this->aggregatorFactory->get($aggregation_function);

    $ret = new ValueCollection();
    if (!$this->measurementExists($measurement)) {
      return $ret;
    }

    $node_ids = array_keys($selection + $order);
    $nodes = $this->getSiblings($node_ids, $selected_levels);

    $selection_nodes = $this->extractSelectionNodes($nodes, $selection, $selected_levels);
    $in_range_selection_nodes = $this->filterOutOfRangeSelectionNodes($selection_nodes, $start, $end);

    foreach ($in_range_selection_nodes as $node) {
      if ($node->selectionType == SelectionType::NODE) {
        if ($node->start < $start) {
          $start = $node->start;
        }
        if ($node->end > $end) {
          $end = $node->end;
        }
      }
    }

    list($selection_nodes_indexes, $start_index_collapse) = $this->calcSelectionNodeIndexes($selection_nodes, $selection, $selected_levels, $start, $end);

    $cond_selection_nodes = array_filter($in_range_selection_nodes, function(Node $node) { return $node->selectionType === SelectionType::NONE; });

    // Build correct select intervals
    $cond = implode(' OR ', array_fill(0, 1+count($cond_selection_nodes),
      sprintf($this->intervalQueryFormat, EpivizApiController::ROWS_TABLE, $partition == null ? '`partition` IS NULL' : '`partition` = ?')));

    $params = array();

    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $start;
    $last_end = $start;
    foreach ($cond_selection_nodes as $node) {
      $params[] = $node->start;
      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $last_end;
      $params[] = $node->start;

      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $node->end;
      $last_end = $node->end;
    }

    $params[] = $end;
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $last_end;
    $params[] = $end;

    $db = $this->db;
    $db->beginTransaction();

    $db->query(sprintf('DROP TABLE IF EXISTS `%1$s`', EpivizApiController::TEMP_ROWS));
    $db->prepare(sprintf('CREATE TEMPORARY TABLE `%1$s` ENGINE=MEMORY AS (SELECT * FROM `%2$s` WHERE %3$s ORDER BY `index` ASC)',
      EpivizApiController::TEMP_ROWS, EpivizApiController::ROWS_TABLE, $cond))->execute($params);

    $db->commit();

    $sql = sprintf(
      'SELECT `val`, `%1$s`.`index`, `%1$s`.`start`, `%1$s`.`end` FROM `%1$s` LEFT OUTER JOIN '
      .'(SELECT `val`, `row`, `col` FROM `%2$s` WHERE '
        .'`col` = (SELECT `index` FROM `%3$s` WHERE `id` = ?)) vals '
      .'ON vals.`row` = `%1$s`.`index` ORDER BY `%1$s`.`index` ASC ',
      EpivizApiController::TEMP_ROWS, EpivizApiController::VALUES_TABLE, EpivizApiController::COLS_TABLE);

    $stmt = $this->db->prepare($sql);
    $stmt->execute(array($measurement));

    list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
    $selection_node = idx($selection_nodes, $selection_node_id);
    $index_collapse = $start_index_collapse;

    $min_index = null;
    $last_index = null;
    $vals = array();
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $v = $r[0] == null ? 0 : round(0 + $r[0], 3);
      $s = 0 + $r[2]; // start
      $e = 0 + $r[3];
      if ($selection_node !== null && $selection_node->selectionType === SelectionType::NODE &&
        $s >= $selection_node->start && $e <= $selection_node->end) {
        $vals[] = $v;
        continue;
      }

      while ($selection_node !== null && $s >= $selection_node->end) {
        if ($selection_node->selectionType === SelectionType::NODE) {
          $ret->add($agg_func->aggregate($vals), $selection_node_index, $selection_node->start, $selection_node->end);
          $vals = array();
        }

        list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes);
        $selection_node = idx($selection_nodes, $selection_node_id);

        if ($selection_node !== null) {
          $index_collapse = $selection_node->leafIndex - $selection_node_index;
        }
      }

      $ret->add($r[0] == null ? 0 : round(0 + $r[0], 3), 0 + $r[1] - $index_collapse, $s, $e);

      if ($ret->count() == 1) { $ret->globalStartIndex -= $index_collapse; }
    }

    if ($selection_node !== null && $selection_node->selectionType === SelectionType::NODE &&
        $selection_node->end > $start && $selection_node->start < $end) {
      $ret->add($agg_func->aggregate($vals), $selection_node_index, $selection_node->start, $selection_node->end);
      $vals = array();
    }

    while (list($selection_node_id, $selection_node_index) = each($selection_nodes_indexes)) {
      $selection_node = $selection_nodes[$selection_node_id];
      if ($selection_node->start >= $end) { break; }

      if ($selection_node->selectionType === SelectionType::NODE) {
        $ret->add($agg_func->aggregate($vals), $selection_node_index, $selection_node->start, $selection_node->end);
        $vals = array();
      }
    }

    // Apply ordering
    if (!empty($order)) {
      $parent_ids = array_flip(array_map(function($node_id) use ($nodes) { return $nodes[$node_id]->parentId; }, array_keys($order)));
      $order_nodes = array_filter($nodes, function(Node $node) use ($parent_ids) { return array_key_exists($node->parentId, $parent_ids); });
      array_walk($order_nodes, function(Node &$node) use ($order) { $node->order = idx($order, $node->id, $node->order); });

      $ordered_interval_tree = new OrderedIntervalTree($order_nodes);
      $ret = $ret->reorder($ordered_interval_tree->orderIntervals($ret));
    }

    return $ret;
  }

  /**
   * @param array $measurements
   * @param int $start
   * @param int $end
   * @param string|null $partition
   * @param array|null $metadata
   * @param bool $retrieve_index
   * @param bool $retrieve_end
   * @param bool $offset_location
   * @param array|null $selection
   * @param array|null $order
   * @param string|null $aggregation_function
   * @param array|null $selected_levels
   * @return array
   */
  public function getCombined(array $measurements, $start, $end, $partition=null, array $metadata=null, $retrieve_index=true, $retrieve_end=true,
                              $offset_location=false, array $selection=null, array $order=null,
                              $aggregation_function=null, array $selected_levels=null) {

    if ($selection === null) { $selection = array(); }
    if ($order === null) { $order = array(); }

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

    $levels = $this->getLevels();

    if ($aggregation_function == null) { $aggregation_function = 'average'; }
    $agg_func = $this->aggregatorFactory->get($aggregation_function);

    if (count($measurements) == 0) {
      return DatasourceTable::createEmpty($measurements, $metadata, $levels, $offset_location, $retrieve_index, $retrieve_end);
    }

    $node_ids = array_keys($selection + $order);
    $nodes = $this->getSiblings($node_ids, $selected_levels);

    $selection_nodes = $this->extractSelectionNodes($nodes, $selection, $selected_levels);
    $in_range_selection_nodes = $this->filterOutOfRangeSelectionNodes($selection_nodes, $start, $end);

    foreach ($in_range_selection_nodes as $node) {
      if ($node->selectionType == SelectionType::NODE) {
        if ($node->start < $start) {
          $start = $node->start;
        }
        if ($node->end > $end) {
          $end = $node->end;
        }
      }
    }

    list($selection_nodes_indexes, $start_index_collapse) = $this->calcSelectionNodeIndexes($selection_nodes, $selection, $selected_levels, $start, $end);

    $cond_selection_nodes = array_filter($in_range_selection_nodes, function(Node $node) { return $node->selectionType === SelectionType::NONE; });

    // Build correct select intervals
    $cond = implode(' OR ', array_fill(0, 1+count($cond_selection_nodes),
      sprintf($this->intervalQueryFormat, EpivizApiController::ROWS_TABLE, $partition == null ? '`partition` IS NULL' : '`partition` = ?')));

    $params = array();

    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $start;
    $last_end = $start;
    foreach ($cond_selection_nodes as $node) {
      $params[] = $node->start;
      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $last_end;
      $params[] = $node->start;

      if ($partition != null) {
        $params[] = $partition;
      }
      $params[] = $node->end;
      $last_end = $node->end;
    }

    $params[] = $end;
    if ($partition != null) {
      $params[] = $partition;
    }
    $params[] = $last_end;
    $params[] = $end;

    $db = $this->db;
    $db->beginTransaction();

    $db->query(sprintf('DROP TABLE IF EXISTS `%1$s`', EpivizApiController::TEMP_ROWS));
    $db->prepare(sprintf('CREATE TEMPORARY TABLE `%1$s` ENGINE=MEMORY AS (SELECT * FROM `%2$s` WHERE %3$s ORDER BY `index` ASC) ',
      EpivizApiController::TEMP_ROWS, EpivizApiController::ROWS_TABLE, $cond))->execute($params);

    $db->query(sprintf('DROP TABLE IF EXISTS `%1$s`', EpivizApiController::TEMP_HIERARCHY));
    $db->query(sprintf(
      'CREATE TEMPORARY TABLE `%1$s` (PRIMARY KEY (`id`)) ENGINE=MEMORY AS (SELECT * FROM `%2$s` WHERE `id` IN (SELECT `id` FROM `%3$s`) %4$s) ',
      EpivizApiController::TEMP_HIERARCHY, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::TEMP_ROWS, $this->nodesOrderBy));

    $db->query(sprintf('DROP TABLE IF EXISTS `%1$s`', EpivizApiController::TEMP_COLS));
    $db->prepare(sprintf(
      'CREATE TEMPORARY TABLE `%1$s` ENGINE=MEMORY AS (SELECT * FROM `%2$s` WHERE `id` IN (%3$s)) ',
      EpivizApiController::TEMP_COLS, EpivizApiController::COLS_TABLE, implode(',', array_fill(0, count($measurements), '?'))))
      ->execute($measurements);

    $db->commit();

    $fields = '`%1$s`.`index` AS `row`, `%1$s`.`start`';
    $fields .= ', `%4$s`.`lineagelabel`, `%4$s`.`lineage`, `%4$s`.`depth`';
    $fields .= ', `%3$s`.`val`, `%2$s`.`index` AS `col`, `%2$s`.`id` AS `measurement`';
    $fields .= ', `%1$s`.`end`';
    foreach ($metadata as $col) {
      $fields .= ', `%1$s`.`' . $col . '`';
    }

    $sql = sprintf(
      'SELECT '.$fields.' '
      .'FROM (`%1$s` JOIN `%4$s` ON `%1$s`.`id` = `%4$s`.`id`) JOIN `%2$s` '
      .'JOIN `%3$s` ON  (`%1$s`.`index` = `%3$s`.`row` AND `%2$s`.`index` = `%3$s`.`col`) '
      .'ORDER BY `%1$s`.`index` ASC ',
      EpivizApiController::TEMP_ROWS, EpivizApiController::TEMP_COLS, EpivizApiController::VALUES_TABLE, EpivizApiController::TEMP_HIERARCHY);

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $unprocessed_tbl = DatasourceTable::createEmpty($measurements, $metadata, $levels, $offset_location, $retrieve_index, $retrieve_end);
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $unprocessed_tbl->addDbRecord($r);
    }
    $unprocessed_tbl->finalize();

    $ret = $unprocessed_tbl->aggregate($selection_nodes, $agg_func, $start, $end);

    // Apply ordering
    if (!empty($order)) {
      $parent_ids = array_flip(array_map(function($node_id) use ($nodes) { return $nodes[$node_id]->parentId; }, array_keys($order)));
      $order_nodes = array_filter($nodes, function(Node $node) use ($parent_ids) { return array_key_exists($node->parentId, $parent_ids); });
      array_walk($order_nodes, function(Node &$node) use ($order) { $node->order = idx($order, $node->id, $node->order); });

      $ordered_interval_tree = new OrderedIntervalTree($order_nodes);

      $ret = $ret->reorder($ordered_interval_tree->orderIntervals($ret));
    }

    return $ret;
  }

  /**
   * @param int $max_count
   * @param array $annotation
   * @return array
   */
  public function getMeasurements($max_count=null, array $annotation=null) {
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
      $max_count ? 'LIMIT '.$max_count : ''
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
    $levels = $this->getLevels();
    $metadata_cols = array_merge($metadata_cols, $levels);
    if (!empty($levels)) { $metadata_cols[] = 'lineage'; }

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

  /**
   * @param int $depth
   * @param string $node_id
   * @param array $selection
   * @param array $order
   * @param array $selected_levels
   * @return Node
   */
  public function getHierarchy($depth, $node_id=null, array $selection=null, array $order=null, array $selected_levels=null) {
    if ($node_id == null) { $node_id = '0-0'; }

    // TODO: After upgrading to PHP 5.4.2, replace the two lines below with the commented line
    // $node_depth = hexdec(explode('-', $node_id)[0]);
    $pair = explode('-', $node_id);
    $node_depth = hexdec($pair[0]);

    $max_depth = $node_depth + $depth;
    $sql = sprintf($this->hierarchyQueryFormat, EpivizApiController::HIERARCHY_TABLE, EpivizApiController::LEVELS_TABLE).$this->nodesOrderBy;
    $stmt = $this->db->prepare($sql);

    $stmt->execute(array(
      '%'.$node_id.'%',
      $max_depth
    ));

    $root = null;
    $node_map = array();
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node = EpivizApiController::createNodeFromDbRecord($r);
      $nodes[$node->id] = $node;
      $id = $node->id;
      $node->selectionType = idx($selected_levels, $node->depth, idx($selection, $id, SelectionType::LEAVES));

      if ($id == $node_id) {
        $root = $node;
        $node_map[$node_id] = &$root;
      } else {
        $parent_id = $r[3];
        $siblings = &$node_map[$parent_id]->children;
        $node_order = idx($order, $id, $node->order);
        $node->order = $node_order;
        $siblings[] = $node;
        $node_map[$id] = &$siblings[count($siblings)-1];
      }
    }

    EpivizApiController::dfs($root, function(&$node) {
      if (empty($node->children)) { return; }
      usort($node->children, function(Node $c1, Node $c2) {
        return signum($c1->order - $c2->order);
      });
    });

    $parent = null;
    if ($root->depth == 0) { $parent = $root; }
    else {
      // TODO: After upgrading to PHP 5.4.2, replace the two lines below with the commented line
      $parents = $this->getNodes(array($root->parentId));
      $parent = $parents[$root->parentId];
      //$parent = $this->getNodes(array($root->parentId))[$root->parentId];
      $parent->children = array($root);
      $parent->selectionType = idx($selected_levels, $parent->depth, idx($selection, $parent->id, SelectionType::LEAVES));
    }

    return $parent;
  }

  /**
   * @param int $depth
   * @param array $node_ids
   * @param array $selection
   * @param array $order
   * @param array $selected_levels
   * @return array
   */
  public function getHierarchies($depth, array $node_ids, array $selection=null, array $order=null, array $selected_levels=null) {

    // TODO: After upgrading to PHP 5.4.2, replace the two lines below with the commented line
    // $max_depths = array_map(function($node_id) { return hexdec(explode('-', $node_id)[0]); }, $node_ids);
    $max_depths = array_map(function($node_id) {
      $pair = explode('-', $node_id);
      return hexdec($pair[0]);
    }, $node_ids);

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

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $node = EpivizApiController::createNodeFromDbRecord($r);
      $id = $node->id;
      $node->selectionType = idx($selected_levels, $node->depth, idx($selection, $id, SelectionType::LEAVES));

      $node_map[$id] = $node;

      $parent_id = $node->parentId;
      if (array_key_exists($parent_id, $node_map)) {
        $siblings = &$node_map[$parent_id]->children;
        $new_order = idx($order, $id, $node->order);
        $node_order = &$node_map[$id]->order;
        $node_order = $new_order;
        $siblings[] = &$node_map[$id];
      }
    }

    $ret = array();
    foreach ($node_ids as $node_id) {
      $node = &$node_map[$node_id];
      EpivizApiController::dfs($node, function(Node &$node) {
        if (empty($node->children)) { return; }
        usort($node->children, function(Node $c1, Node $c2) {
          return signum($c1->order - $c2->order);
        });
      });
      $ret[$node_id] = $node;
    }

    return $ret;
  }

  /**
   * @return array
   */
  public function getPartitions() {
    $sql = sprintf($this->partitionsQueryFormat, EpivizApiController::ROWS_TABLE);

    $stmt = $this->db->prepare($sql);
    $stmt->execute();

    $partitions = array();
    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      $partitions[] = $r;
    }

    return $partitions;
  }
}
