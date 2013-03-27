<?php namespace Sqobot;

use Exception;
use PDO;

class Error extends Exception {
  public $object;

  static function re(Exception $previous, $append = '') {
    throw static::wrap($previous, $append);
  }

  static function wrap(Exception $previous, $append = '') {
    $msg = $previous->getMessage();
    $append and $msg = "$append\n$msg";
    $object = isset($previous->object) ? $previous->object : null;
    return new static($object, $msg, $previous);
  }

  function __construct($object, $msg = null, Exception $previous = null) {
    $msg === null and S::swap($object, $msg);
    $this->object = $object;
    parent::__construct($msg, 0, $previous);
  }
}

class EQuery extends Error {
  static function exec(\PDOStatement $stmt, $returnStmt = false) {
    if (!$stmt->execute()) {
      throw new static($stmt, "Error executing PDO statement:\n  ".$stmt->queryString);
    } elseif ($returnStmt) {
      return $stmt;
    }

    $head = substr(' '.ltrim($stmt->queryString), 0, 20);

    if (strpos($head, ' INSERT ') !== false) {
      return db()->lastInsertId();
    } elseif (strpos($head, ' UPDATE ') !== false or
              strpos($head, ' DELETE ') !== false) {
      return $stmt->rowCount();
    } else {
      return $stmt;
    }
  }
}

class EDbImport extends EQuery {
  public $sql;
  public $affected;
  public $remainingSQLs;
}

class EAtoms extends Error { }
class ENoTask extends Error { }
class ETaskError extends Error { }
class EWrongURL extends Error { }
class EDownload extends Error { }
class ERegExpError extends Error { }
  class ERegExpNoMatch extends ERegExpError { }
  class ERegExpMismatch extends ERegExpError { }
class ESqissor extends Error { }
  class ENoSqissor extends ESqissor { }

class Core {
  //= hash of mixed
  static $config = array();
  //= null, PDO
  static $pdo;
  //= null for web, array of array 'values', 'index', 'flags'
  static $cl;
  //= array of callable
  static $onFatal = array();
  //= hash of array of callback
  static $events = array();

  static function loadConfig($file) {
    if (is_file($file) and $data = file_get_contents($file)) {
      static::$config = static::parseExtConf($data) + static::$config;
    }
  }

  // From http://proger.i-forge.net/Various_format_parsing_functions_for_PHP/ein.
  //* $str string to parse
  //* $prefix string to prepend for every key in the returned array
  //* $unescape bool - if set \XX sequences will be converted to characters in value
  //= array of 'key' => 'value' pairs
  static function parseExtConf($str, $prefix = '', $unescape = false) {
    $result = array();

    $block = null;
    $value = '';

    foreach (explode("\n", $str) as $line) {
      if ($block === null) {
        $line = trim($line);

        if ($line !== '' and strpbrk($line[0], '#;') === false) {
          @list($key, $value) = explode('=', $line, 2);

          $key = rtrim($key);
          $value = ltrim($value);

          if ($value === '{') {
            $block = $key;
            $value = '';
          } elseif (isset($value)) {
            $result[$prefix.$key] = $unescape ? stripcslashes($value) : $value;
          }
        }
      } elseif ($line === '}') {
        $result[$prefix.$block] = (string) substr($value, 0, -1);
        $block = null;
      } else {
        $value .= rtrim($line)."\n";
      }
    }

    return $result;
  }

  static function cli() {
    return defined('STDIN') or !empty($_SERVER['TERM']);
  }
}

function dd() {
  if (!Core::cli() and !extension_loaded('xdebug')) { echo '<pre>'; }

  func_num_args() or print str_repeat('-', 79).PHP_EOL;
  foreach (func_get_args() as $arg) { var_dump($arg); }
  echo PHP_EOL, PHP_EOL;

  if (defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  } else {
    debug_print_backtrace();
  }

  exit(1);
}

function cfg($name, $wrap = null) {
  $value = (string) S::pickFlat(Core::$config, $name);

  if ($value === '' or !isset($wrap)) {
    return $value;
  } else {
    return str_replace('$', $value, $wrap);
  }
}

function cfgGroup($group) {
  $group .= ' ';
  return S::build(Core::$config, function ($value, $name) use ($group) {
    if (S::unprefix($name, $group)) { return array(trim($name) => $value); }
  });
}

function remoteDelay($started = null, $delay = null) {
  isset($delay) or $delay = cfg('remoteDelay');
  $delay += mt_rand(0, $delay / 10);

  if ($started === null) {
    usleep(1000 * $delay);
  } elseif ($started === true) {
    return microtime(true);
  } else {
    $now = microtime(true);
    $delay -= round(($now - $started) * 1000);
    $delay >= 10 and usleep(1000 * $delay);
    return $now;
  }
}

function opt($name = null, $default = null) {
  if ($name === null) {
    return Core::$cl['index'];
  } else {
    $group = is_int($name) ? 'index' : 'options';

    if (isset(Core::$cl[$group][$name])) {
      return Core::$cl[$group][$name];
    } else {
      return S::unclosure($default);
    }
  }
}

function hook($event, $callback) {
  Core::$events[$event][] = $callback;
}

function fire($event, $args = array()) {
  if ($callbacks = &Core::$events[$event]) {
    is_array($args) or $args = array($args);

    foreach ($callbacks as $callback) {
      $result = call_user_func_array($callback, $args);
      if (isset($result)) { return $result; }
    }
  }
}

function log($msg, $level = 'info') {
  if (strpos(cfg('log', ' $ '), " $level ") !== false and $log = logFile()) {
    $client = Core::$cl ? 'cli' : S::pickFlat($_SERVER, 'REMOTE_ADDR');
    $user = Web::user() and $client .= " $user";

    $msg = sprintf('$ %s [%s] [%s] %s', strtoupper($level), date('H:i:s d-m-Y'),
                   $client, $msg);

    S::mkdirOf($log);
    touch($log);
    file_put_contents($log, "$msg\n\n", FILE_APPEND);
  }
}

function logFile() {
  return strftime( opt('log', cfg('logFile')) );
}

function warn($msg) {
  log($msg, 'warn');
}

function error($msg) {
  log($msg, 'error');
}

function exLine(Exception $e) {
  return sprintf('%s (in %s:%d)', $e->getMessage(), $e->getFile(), $e->getLine());
}

function db() {
  if (!Core::$pdo) {
    $pdo = Core::$pdo = new PDO(cfg('dbDSN'), cfg('dbUser'), cfg('dbPassword'));

    $charset = cfg('dbConCharset') and $pdo->exec('SET NAMES '.$charset);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
    $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
    $pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
  }

  return Core::$pdo;
}

// Ignores blank commands (';;') and comments ('--...').
function dbImport($sqls) {
  is_array($sqls) or $sqls = explode(';', $sqls);

  $sum = 0;

  foreach ($sqls as $i => $sql) {
    $sql = trim(preg_replace('~^[ \t]*--([ \t].*)?~mu', '', $sql));

    try {
      $sql and $sum += db()->exec($sql);
    } catch (\Exception $e) {
      $e = EDbImport::wrap($e, "Error running $sql.");
      $e->sql = $sql;
      $e->affected = $sum;
      $e->remainingSQLs = array_slice($sqls, $i + 1);
      throw $e;
    }
  }

  return $sum;
}

function atomic($func) {
  // Fatal Errors are handled by Atoms so no rolling back on them.
  $atomate = Atoms::enabled() and Atoms::enter();

  if (db()->inTransaction()) {
    try {
      $result = call_user_func($func);
      $atomate and Atoms::commit();
      return $result;
    } catch (\Exception $e) {
      $atomate and Atoms::rollback();
      throw $e;
    }
  } else {
    db()->beginTransaction();

    return rescue(
      function () use ($func, $atomate) {
        $result = call_user_func($func);
        db()->commit();
        $atomate and Atoms::commit();
        return $result;
      },
      function ($e, $fatal) use ($atomate) {
        !$fatal and $atomate and Atoms::rollback();
        // when this exception handler is called by a Fatal Error transaction
        // has already been rolled back by PHP.
        db()->inTransaction() and db()->rollBack();
      }
    );
  }
}

function prep($sql, $bind = array()) {
  $stmt = db()->prepare($sql);

  foreach (S::arrizeAny($bind) as $name => $value) {
    is_int($name) and ++$name;

    if (is_string($value)) {
      $type = PDO::PARAM_STR;
    } elseif (is_int($value) or is_float($value) or is_bool($value)) {
      $type = PDO::PARAM_INT;
    } elseif ($value === null) {
      $type = PDO::PARAM_NULL;
    } else {
      $type = gettype($value);
      throw new Error("Wrong value type $type to bind to :$name passed to prep().");
    }

    $stmt->bindValue($name, $value, $type);
  }

  return $stmt;
}

//= int last insert ID for INSERTs, PDOStatement for others
function exec($sql, $bind = array()) {
  return EQuery::exec(prep($sql, $bind));
}

function toTimestamp($time) {
  if (is_object($time)) {
    return $time->getTimestamp();
  } elseif (is_numeric($time)) {
    return (int) $time;
  } else {
    return (int) strtotime($time);
  }
}

//= DOMDocument
function parseXML($str) {
  $doc = new \DOMDocument;
  if ($doc->loadXML($str)) {
    return $doc;
  } else {
    throw new Error('Cannot parse string as XML.');
  }
}

//* $headers hash of str/array, str 'Referer'
function download($url, $headers = array()) {
  return Download::it($url, $headers);
}

function realURL($url) {
  $met = array($url => true);
  $trace = function () use (&$met) { return join(' -> ', array_keys($met)); };

  while ($target = cfg("url $url")) {
    if (isset($met[$target])) {
      error("Curricular URL mapping that depends on itself - using original URL: ".
            $trace()." -> $target.");
      return key($met);
    }

    $met[$target] = true;
    $url = $target;
  }

  if (!$url) {
    throw new EWrongURL('Empty URL given to realURL().');
  } elseif (strrchr(substr($url, 0, 20), ':') === false and $url[0] !== '/') {
    return 'file:///'.trim(strtr(getcwd(), '\\', '/'), '/')."/$url";
  } else {
    return $url;
  }
}

function taskURL($task, array $query = array()) {
  return '.'.S::queryStr(compact('task') + $query);
}

function onFatal($func, $name = null) {
  if (!$name) {
    $name = count(Core::$onFatal);
    while (isset(Core::$onFatal[$name])) { ++$name; }
  }

  Core::$onFatal[$name] = $func;
  return $name;
}

function offFatal($func) {
  if (is_scalar($func)) {
    unset(Core::$onFatal[$func]);
  } else {
    foreach (Core::$onFatal as $key => $item) {
      if ($item === $func) { unset(Core::$onFatal[$key]); }
    }
  }
}

function rescue($body, $error, $finally = null) {
  $catch = function ($e, $catchable = false) use (&$id, $error, $finally) {
    offFatal($id);
    $finally and call_user_func($finally, $e);
    $error and call_user_func($error, $e, $catchable !== true);
  };

  $id = onFatal($catch);

  try {
    $result = call_user_func($body);
    offFatal($id);
    $finally and call_user_func($finally);
    return $result;
  } catch (Exception $e) {
    $catch($e, true);
    throw $e;
  }
}
