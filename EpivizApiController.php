<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:56 PM
 */

namespace epiviz\api;

use PDO;

class EpivizApiController {

  const ROWS_TABLE = 'row_data';
  const VALUES_TABLE = 'values';
  const COLS_TABLE = 'col_data';

  private $rowsQueryFormat;
  private $valsQueryFormat;
  private $db;
  private $tablesColumns = array();

  public function __construct() {
    $this->rowsQueryFormat =
      'SELECT %1$s FROM %2$s WHERE `index` BETWEEN '
      .'(SELECT MIN(`index`) FROM %2$s WHERE %3$s AND `start` < :end1 AND `end` >= :start1) AND '
      .'(SELECT MAX(`index`) FROM %2$s WHERE %4$s AND `start` < :end2 AND `end` >= :start2) ORDER BY `index` ASC; ';

    $this->valsQueryFormat =
      'SELECT `val`, `%1$s`.`index` FROM `%1$s` LEFT OUTER JOIN '
        .'(SELECT `val`, `row`, `col` FROM `%2$s` JOIN `%3$s` ON `col` = `index` WHERE `%3$s`.`id` = :measurement) vals '
        .'ON vals.`row` = `%1$s`.`index` '
      .'WHERE `%1$s`.`index` BETWEEN '
        .'(SELECT MIN(`index`) FROM `%1$s` WHERE %4$s AND `start` < :end1 AND `end` >= :start1) AND '
        .'(SELECT MAX(`index`) FROM `%1$s` WHERE %5$s AND `start` < :end2 AND `end` >= :start2) '
      .'ORDER BY `%1$s`.`index` ASC;';

    $this->db = (new EpivizDatabase())->db();
  }

  private function getTableColumns($table_name) {
    if (!array_key_exists($table_name, $this->tablesColumns)) {
      $rows = $this->db->query("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_NAME`='$table_name';");
      $columns = array();
      while (($r = ($rows->fetch(PDO::FETCH_NUM))) != false) {
        $columns[] = $r[0];
      }
      $this->tablesColumns[$table_name] = array_flip($columns);
    }
    return $this->tablesColumns[$table_name];
  }

  public function getRows($start, $end, $partition, $metadata, $retrieve_index, $retrieve_end, $offset_location) {
    /*echo json_encode(array(
      'partition' => $partition,
      'start' => $start,
      'end' => $end,
      'metadata' => $metadata,
      'retrieve_index' => $retrieve_index,
      'retrieve_end' => $retrieve_end,
      'offset_location' => $offset_location
    ));
    return;*/

    $retrieve_index = ($retrieve_index != 'false');
    $retrieve_end = ($retrieve_end != 'false');
    $offset_location = ($offset_location == 'true');
    $metadata_cols = empty($metadata) ? null : explode(',', $metadata);
    $partition = empty($partition) ? null : $partition;

    $fields = '`index`, `start`';
    $metadata_cols_index = 2;
    if ($retrieve_end) { $fields .= ', `end`'; ++$metadata_cols_index; }

    $params = array(
      'start1' => $start,
      'start2' => $start,
      'end1' => $end,
      'end2' => $end);
    if ($partition != null) {
      $params['part1'] = $partition;
      $params['part2'] = $partition;
    }

    $values = array(
      'index' => $retrieve_index ? array() : null,
      'start' => array(),
      'end' => $retrieve_end ? array() : null,
    );

    // Compress the sent data so that the message is sent a faster over the internet
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
    if ($metadata_cols != null) {
      $safe_metadata_cols = array();
      foreach ($metadata_cols as $col) {
        if (array_key_exists($col, $columns) && !array_key_exists($col, $location_cols)) {
          $safe_metadata_cols[] = $col;
        }
      }
      $metadata_cols = $safe_metadata_cols;
    } else {
      $metadata_cols = array();
      foreach ($columns as $col => $_) {
        if (!array_key_exists($col, $location_cols)) {
          $metadata_cols[] = $col;
        }
      }
    }

    foreach ($metadata_cols as $col) {
      $fields .= ', `' . $col . '`';
    }

    if (!empty($metadata_cols)) {
      $values['metadata'] = array();
      foreach ($metadata_cols as $col) {
        $values['metadata'][$col] = array();
      }
    }

    $sql = sprintf($this->rowsQueryFormat,
      $fields,
      EpivizApiController::ROWS_TABLE,
      $partition == null ? '`partition` IS NULL' : '`partition` = :part1',
      $partition == null ? '`partition` IS NULL' : '`partition` = :part2'
    );

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    while (!empty($stmt) && ($r = ($stmt->fetch(PDO::FETCH_NUM))) != false) {
      if ($min_index === null) { $min_index = 0 + $r[0]; }
      if ($retrieve_index) { $values['index'][] = 0 + $r[0]; }

      $start = 0 + $r[1];
      $end = $retrieve_end ? 0 + $r[2] : null;

      if ($offset_location) {
        if ($last_start !== null) {
          $start -= $last_start;
          if ($retrieve_end) { $end -= $last_end; }
        }

        $last_start = 0 + $r[1];
        if ($retrieve_end) { $last_end = 0 + $r[2]; }
      }

      $values['start'][] = $start;
      if ($retrieve_end) { $values['end'][] = $end; }
      if (!empty($metadata_cols)) {
        $col_index = $metadata_cols_index;
        foreach ($metadata_cols as $col) {
          $values['metadata'][$col][] = $r[$col_index++];
        }
      }
    }
    $data = array(
      'values' => $values,
      'globalStartIndex' => $min_index,
      'useOffset' => $offset_location
    );

    echo json_encode($data);
  }

  public function getValues($measurement, $start, $end, $partition) {
    $partition = empty($partition) ? null : $partition;
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

    echo json_encode($data);
  }

  //public function getAnnotation
}