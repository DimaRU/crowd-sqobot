<?php namespace Sqobot;

abstract class Task {
  public $name;

  static function make($task) {
    $class = static::factory($task);
    return new $class;
  }

  static function factory($task, $fail = true) {
    $class = NS.'Task'.ucfirst(strtolower(trim($task)));

    if (class_exists($class)) {
      return $class;
    } elseif ($fail) {
      throw new ENoTask("Unknown task [$task] - class [$class] is undefined.");
    }
  }

  function __construct() {
    $this->name = S::tryUnprefix(get_class($this), __CLASS__);
  }

  function do_(array $args = null) {
    return print $this->name.' task has no default method.';
  }

  function start() { }
  function end() { }
  function before(&$task, array &$args = null) { }
  function after($task, array $args = null, &$result) { }

  function capture($task, $args = array()) {
    ob_start();
    $this->call($task, $args);
    return ob_get_clean();
  }

  // Typically returns an integer - exit code.
  function call($task, $args = array()) {
    $func = strtolower("do_$task");
    $id = get_class($this)."->$func";

    if (!method_exists($this, $func)) {
      throw new ENoTask($this, "Task method $id doesn't exist.");
    }

    $args === null or $args = S::arrize($args);
    $this->before($task, $args);

    try {
      log("Begin {$this->name} $task ". opt(0));
      $started = microtime(true);
      $result = $this->$func($args);
      $duration = microtime(true) - $started;
      log("End {$this->name} $task ". opt(0). 
              sprintf('. Execution time %1.2f sec. ', $duration). 
              "Downloads/timeouts/wait: ".Download::$requests."/".Download::$timeouts."/".Download::$starttransfer_times);
    } catch (\Exception $e) {
      ETaskError::re($e, "Exception while running task [$id].");
    }

    $this->after($task, $args, $result);
    return $result;
  }

  //= array of str '' (default), 'unpack', ... suitable for call()
  function methods() {
    return S::build(get_class_methods($this), function ($func) {
      if (S::unprefix($func, 'do_')) { return $func; }
    });
  }
}