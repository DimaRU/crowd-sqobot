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
      $rustart = getrusage();
      $result = $this->$func($args);
      $ruend = getrusage();
      $duration = microtime(true) - $started;
      $utime = $ruend["ru_utime.tv_sec"] - $rustart["ru_utime.tv_sec"];
      $stime = $ruend["ru_stime.tv_sec"] - $rustart["ru_stime.tv_sec"];
      log("End {$this->name} $task ". opt(0). '.');
      log(sprintf('Execution time(total/cpu user/cpu sys): %1.0f/%1.0f/%1.0f sec.', $duration, $utime, $stime));
      log(sprintf('Memory used %5.2f Mb. ', round(memory_get_peak_usage(true)/1048576,2)));
      log(sprintf('Downloads/MBytes/timeouts/wait: %d/%.3f/%d/%.0f', 
              Download::$requests, Download::$size_download/1048576, Download::$timeouts, Download::$starttransfer_times));
      log("Rows created/updated: " . Row::$createdRows . "/" . Row::$updatedRows . '.');
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