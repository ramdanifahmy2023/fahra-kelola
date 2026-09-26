<?php

class BaseModel {
  // Database Instance
  protected $db;

  // Table Name
  protected $table = '';

  // Constructor
  public function __construct() {
    $this->db = new Database();
  }

  // Find All Records
  public function findAll() {
    $this->db->query("SELECT * FROM {$this->table}");
    return $this->db->getAll();
  }

  // Find Single Record By Column
  public function findBy($column, $value) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :{$column}");
    $this->db->bind($column, $value);
    return $this->db->single();
  }

  // Find All Records By Column
  public function findAllBy($column, $value) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :{$column}");
    $this->db->bind($column, $value);
    return $this->db->getAll();
  }

  // Insert Record
  public function insert($data = []) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $this->db->query("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");
    
    foreach ($data as $key => $value) {
      $this->db->bind($key, $value);
    }
    
    $this->db->exe();
    return $this->db->row();
  }

  // Update Record
  public function update($id, $data = []) {
    $fields = [];
    foreach ($data as $key => $value) {
      $fields[] = "{$key} = :{$key}";
    }
    $setString = implode(', ', $fields);

    $this->db->query("UPDATE {$this->table} SET {$setString} WHERE id = :id");
    
    $this->db->bind('id', $id);
    foreach ($data as $key => $value) {
      $this->db->bind($key, $value);
    }

    $this->db->exe();
    return $this->db->row();
  }

  // Delete Record
  public function delete($id) {
    $this->db->query("DELETE FROM {$this->table} WHERE id = :id");
    $this->db->bind('id', $id);
    
    $this->db->exe();
    return $this->db->row();
  }

  // Find Records With Multiple Conditions
  public function findWhere($conditions = []) {
    $whereParts = [];
    foreach ($conditions as $key => $value) {
      $whereParts[] = "{$key} = :{$key}";
    }
    $whereString = implode(' AND ', $whereParts);
    
    $this->db->query("SELECT * FROM {$this->table} WHERE {$whereString}");
    foreach ($conditions as $key => $value) {
      $this->db->bind($key, $value);
    }
    return $this->db->getAll();
  }

  // Search Records
  public function search($column, $keyword) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} LIKE :keyword");
    $this->db->bind('keyword', "%{$keyword}%");
    return $this->db->getAll();
  }

  // Count Total Records
  public function count() {
    $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
    return $this->db->single()['total'];
  }

  // Count Records With Multiple Conditions
  public function countWhere($conditions = []) {
    if (empty($conditions)) {
      return $this->count();
    }
    
    $whereParts = [];
    foreach ($conditions as $key => $value) {
      $whereParts[] = "{$key} = :{$key}";
    }
    $whereString = implode(' AND ', $whereParts);
    
    $this->db->query("SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereString}");
    foreach ($conditions as $key => $value) {
      $this->db->bind($key, $value);
    }
    return $this->db->single()['total'];
  }

  // Find Records With Multiple Conditions Paginated
  public function findWherePaginated($conditions = [], $limit = 10, $offset = 0, $orderBy = 'id DESC') {
    $limit = (int) $limit;
    $offset = (int) $offset;
    
    if (empty($conditions)) {
      $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}");
      return $this->db->getAll();
    }
    
    $whereParts = [];
    foreach ($conditions as $key => $value) {
      $whereParts[] = "{$key} = :{$key}";
    }
    $whereString = implode(' AND ', $whereParts);
    
    $this->db->query("SELECT * FROM {$this->table} WHERE {$whereString} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}");
    foreach ($conditions as $key => $value) {
      $this->db->bind($key, $value);
    }
    return $this->db->getAll();
  }
}
