<?php

class Database {
  // Database Configuration
  private $host = DB_HOST;
  private $user = DB_USER;
  private $pass = DB_PASS;
  private $db_name = DB_NAME;

  // Database Handler and Statement
  private $dbh;
  private $stmt;

  // Constructor
  public function __construct() {
    $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name;
    $option = [
      PDO::ATTR_PERSISTENT => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    try {
      $this->dbh = new PDO($dsn, $this->user, $this->pass, $option);
    } catch (PDOException $e) {
      die($e->getMessage());
    }
  }

  // Query Statement
  public function query($query) {
    $this->stmt = $this->dbh->prepare($query);
  }

  // Bind Parameters
  public function bind($param, $value, $type = null) {
    if (is_null($type)) {
      switch (true) {
        case is_int($value):
          $type = PDO::PARAM_INT;
          break;
        case is_bool($value):
          $type = PDO::PARAM_BOOL;
          break;
        case is_null($value):
          $type = PDO::PARAM_NULL;
          break;
        default:
          $type = PDO::PARAM_STR;
      }
    }
    $this->stmt->bindValue($param, $value, $type);
  }

  // Execute Statement
  public function exe() {
    $this->stmt->execute();
  }

  // Fetch All Results
  public function getAll() {
    $this->exe();
    return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // Fetch Single Result
  public function single() {
    $this->exe();
    return $this->stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Row Count
  public function row() {
    return $this->stmt->rowCount();
  }

  // Last Insert ID
  public function lastId() {
    return $this->dbh->lastInsertId();
  }

  // Transaction Begin
  public function begin() {
    $this->dbh->beginTransaction();
  }

  // Transaction Commit
  public function commit() {
    $this->dbh->commit();
  }

  // Transaction Rollback
  public function rollback() {
    $this->dbh->rollBack();
  }
}
