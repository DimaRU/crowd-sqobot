<?php namespace Sqobot;

class Row {
  static $defaultTable;
  static $table;
  
  private $row;
  private $where;
  public $id;

  static function setTableName($table = null) {
    if (!$table) {
      if ($table = static::$defaultTable) {
        static::$table = cfg('dbPrefix').static::$defaultTable;
      } else {
        $class = get_called_class();
        throw new Error("No default table specified for Row class $class.");
      }
    } else {
        static::$table = $table;
    }
    return static::$table;
  }

  function getTableName() {
    if (!static::$table) {
      if (static::$defaultTable) {
        static::$table = cfg('dbPrefix').static::$defaultTable;
      } else {
        $class = get_called_class();
        throw new Error("No default table specified for Row class $class.");
      }
    }
    return static::$table;
  }

  static function make($fields = array(), $where = array()) {
    return new static($fields, $where);
  }

  //* $fields stdClass, hash
  function __construct($fields = array(), $where = array()) {
    $this->defaults();
    $this->row = $fields;
    $this->where = $where;
  }

  // Must return $this.
  function defaults() {
    return $this;
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
    
    return $this;
  }

  // See update(), updateWith(), updateIgnore() and others.
  protected function doUpdate($method, $sqlVerb) {
    list($fields, $bind) = S::divide($this->row);
    list($fieldsw, $bindw) = S::divide($this->where);

    $sql = "$sqlVerb `".$this->getTableName().'` SET '.join(', ', S($fields, '"`?` = ??"')).
           ' WHERE '.join('AND ', S($fieldsw, '"`?` = ??"'));
    exec($sql, array_merge($bind, $bindw));
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