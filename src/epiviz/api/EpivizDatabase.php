<?php
/**
 * Created by Florin Chelaru ( florin [dot] chelaru [at] gmail [dot] com )
 * Date: 6/15/2015
 * Time: 10:03 PM
 */

namespace epiviz\api;

use PDO;

class EpivizDatabase {

  private $db;

  public function db() {
    if ($this->db == null) {
      $this->db = new PDO(
        'mysql:host='.Config::SERVER.';dbname='.Config::DATABASE.';charset=utf8',
        Config::USERNAME, Config::PASSWORD,
        array(
          PDO::ATTR_PERSISTENT => true,
          PDO::ATTR_EMULATE_PREPARES => false, // Used to prevent SQL injection
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ));
    }

    return $this->db;
  }
}