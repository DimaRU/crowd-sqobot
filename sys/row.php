<?php namespace Sqobot;

class Row {
  static $createdRows = 0;
  static $updatedRows = 0;
  
  static $table;        // table name
  static $key;          // primary key name
  
  private $row;
  private $where;
  
  public $id;

  // Set table name and primary key
  static function setTableNameKey($table, $key = null) {
    static::$table = $table;
    static::$key = $key;
  }

  function getTableName() {
    if (!static::$table) {
        throw new Error("No table specified for Row.");
    }
    return static::$table;
  }

  function getKey() {
    if (!static::$key) {
        throw new Error("No primary key specified for Row.");
    }
    return static::$key;
  }

  
  static function make($fields = array(), $where = array()) {
    return new static($fields, $where);
  }

  //* $fields stdClass, hash
  function __construct($fields = array(), $where = array()) {
    $this->row = $fields;
    $this->where = $where;
  }

  static function count(array $fields = null) {
    $sql = 'SELECT COUNT(1) AS count FROM `'.static::$table.'`';
    $fields and $sql .= ' WHERE '.join(' AND ', S($fields, '#"`?` = ??"'));

    $stmt = exec($sql, array_values((array) $fields));
    $count = $stmt->fetch()->count;
    $stmt->closeCursor();

    return $count;
  }

  // See create(), createWith(), createIgnore() and others.
  protected function doCreate($method, $sqlVerb) {
    list($fields, $bind) = S::divide($this->row);

    $sql = "$sqlVerb INTO `".$this->getTableName().'`'.
           ' (`'.join('`, `', $fields).'`) VALUES'.
           ' ('.join(', ', S($bind, '"??"')).')';
    $this->id = exec($sql, $bind);
    self::$createdRows++;
    return $this;
  }

  // See update(), updateWith(), updateIgnore() and others.
  protected function doUpdate($method, $sqlVerb) {
    list($fields, $bind) = S::divide($this->row);
    list($fieldsw, $bindw) = S::divide($this->where);

    $sql = "$sqlVerb `".$this->getTableName().'` SET '.join(', ', S($fields, '"`?` = ??"')).
           ' WHERE '.join('AND ', S($fieldsw, '"`?` = ??"'));
    exec($sql, array_merge($bind, $bindw));
    self::$updatedRows++;
    return $this;
  }
  
  protected function createOrUpdate() {
    list($fieldsc, $bindc) = S::divide($this->row);
    unset($this->row[$this->getKey()]);
    list($fieldsu, $bindu) = S::divide($this->row);

    $sql = "INSERT INTO `".$this->getTableName().'`'.
           ' (`'.join('`, `', $fieldsc).'`) VALUES'.
           ' ('.join(', ', S($bindc, '"??"')).')'.
           ' ON DUPLICATE KEY UPDATE '.
            join(', ', S($fieldsu, '"`?` = ??"'))
            ;
    $this->id = exec($sql, array_merge($bindc, $bindu));
    self::$createdRows++;
    return $this;

  }
  
  /*---------------------------------------------------------------------
  | RECORD MANIPULATION VERBS
  |--------------------------------------------------------------------*/

  //= Row new entry
  static function createWith($fields) {
    return static::make($fields)->create();
  }

  //= Row new entry
  static function createIgnoreWith($fields) {
    return static::make($fields)->createIgnore();
  }

  //= Row new entry
  static function createOrReplaceWith($fields) {
    return static::make($fields)->createOrReplace();
  }

  //= Row new entry
  static function createOrUpdateWith($fields) {
    return static::make($fields)->createOrUpdate();
  }
  
  //= Row updated entry
  static function updateWith($fields, $where) {
    return static::make($fields, $where)->update();
  }

  //= Row updated entry
  static function updateIgnoreWith($fields, $where) {
    return static::make($fields, $where)->updateIgnore();
  }

  //= $this
  function create() {
    return $this->doCreate(__FUNCTION__, 'INSERT');
  }

  //= $this
  function createIgnore() {
    return $this->doCreate(__FUNCTION__, 'INSERT IGNORE');
  }

  //= $this
  function createOrReplace() {
    return $this->doCreate(__FUNCTION__, 'REPLACE');
  }

  //= $this
  function update() {
    return $this->doUpdate(__FUNCTION__, 'UPDATE');
  }

  //= $this
  function updateIgnore() {
    return $this->doUpdate(__FUNCTION__, 'UPDATE IGNORE');
  }
}