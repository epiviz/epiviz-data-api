<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 8:56 PM
 */

namespace epiviz\api;


class EpivizApiController {
  public function getRows($partition, $start, $end, $metadata='') {
    $db = (new EpivizDatabase())->db();
    // TODO: Aici am ramas: get rows from database

    echo json_encode(array(
      'partition' => $partition,
      'start' => $start,
      'end' => $end,
      'metadata' => $metadata
    ));
  }
}