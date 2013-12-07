<?php namespace Sqobot;

class Download {
  static $maxFetchSize = 20971520;      // 20 MiB

  public $contextOptions = array();
  public $url;
  public $headers;

  public $handle;
  public $responseHeaders;
  public $reply;

  static function it($url, $headers = array()) {
    return static::make($url, $headers)->fetchData();
  }

  static function make($url, $headers = array()) {
    return new static($url, $headers);
  }

  function __construct($url, $headers = array()) {
    $this->url($url);

    is_array($headers) or $headers = array('referer' => $headers);
    $this->headers = array_change_key_case($headers);

    $this->contextOptions = array(
      'follow_location'   => cfg('dlRedirects') > 0,
      'max_redirects'     => max(0, (int) cfg('dlRedirects')),
      'protocol_version'  => cfg('dlProtocol'),
      'timeout'           => (float) cfg('dlTimeout'),
      'ignore_errors'     => !!cfg('dlFetchOnError'),
    );
  }

  function __destruct() {
    $this->close();
  }

  function url($new = null) {
    if ($new) {
      if (!filter_var($new, FILTER_VALIDATE_URL)) {
        throw new \InvalidArgumentException("[$new] doesn't look like a valid URL.");
      }

      $this->url = $new;
      return $this;
    } else {
      return $this->url;
    }
  }

  function urlPart($part) {
    return parse_url($this->url, $part);
  }

  function open() {
    if (!$this->handle) {
      $context = $this->createContext();
      $this->handle = fopen($this->url, 'rb', false, $context);

      $context and $this->opened($context, $this->handle);

      if (!$this->handle) {
        throw new RuntimeException("Cannot fopen({$this->url}).");
      }

      $this->responseHeaders = (array) stream_get_meta_data($this->handle);
    }

    return $this;
  }

  //* $context resource of stream_context_create()
  //* $file resource of fopen(), false if failed
  protected function opened($context, $file = null) {
    if ($log = static::logFile()) {
      if (is_file($log) and filesize($log) >= S::size(cfg('dlLogMax'))) {
        file_put_contents($log, '', LOCK_EX);
      }

      S::mkdirOf($log);
      $info = static::summarize($this->url, $context, $file);
      $ok = file_put_contents($log, "$info\n\n", LOCK_EX | FILE_APPEND);
      $ok or warn("Cannot write to dlLog file [$log].");
    }
  }

  function read() {
    $limit = min(static::$maxFetchSize, PHP_INT_MAX);

    $this->reply = stream_get_contents($this->open()->handle, $limit, -1);
    if (!is_string($this->reply)) {
      throw new RuntimeException("Cannot get remote stream contents of [{$this->url}].");
    }

    return $this;
  }

  function close() {
    $h = $this->handle and fclose($h);
    $this->handle = null;
    return $this;
  }

  function fetchData() {
    return $this->read()->close()->reply;
  }

  function createContext() {
    $options = array('http' => $this->contextOptions());
    return stream_context_create($options);
  }

  function contextOptions() {
    $options = $this->contextOptions;
    return array('header' => $this->normalizeHeaders()) + $options;
  }

//= array of scalar like 'Accept: text/html'
  function normalizeHeaders() {
    foreach (get_class_methods($this) as $func) {
      if (substr($func, 0, 7) === 'header_') {
        $header = strtr(substr($func, 7), '_', '-');

        if (!isset( $this->headers[$header] )) {
          $this->headers[$header] = $this->$func();
        }
      }
    }

    $result = array();

    foreach ($this->headers as $header => $value) {
      if (!is_int($header)) {
        $header = preg_replace('~(^|-).~e', 'strtoupper("\\0")', strtolower($header));
      }

      if (is_array($value)) {
        if (!is_int($header)) {
          foreach ($value as &$s) { $s = "$header: "; }
        }

        $result = array_merge($result, array_values($value));
      } elseif (is_int($header)) {
        $result[] = $value;
      } elseif (($value = trim($value)) !== '') {
        $result[] = "$header: $value";
      }
    }

    return $result;
  }

  function has($header) {
    return isset($this->headers[$header]);
  }

  function header_accept_language($str = '') {
    return cfg('dl languages');
  }

  function header_accept_charset($str = '') {
    return cfg('dl charsets');
  }

  function header_accept($str = '') {
    return cfg('dl mimes');
  }

  function header_user_agent() {
    return cfg('dl useragent');
  }

  function header_cache_control() {
    return cfg('dl cache');
  }

  function header_referer() {
    return 'http://'.$this->urlPart(PHP_URL_HOST).'/';
  }
  
  
  
    static function logFile() {
    return strftime( opt('dlLog', cfg('dlLog')) );
  }

  // Create log record
  static function summarize($url, $context, $file = null) {
    $meta = $file ? stream_get_meta_data($file) : array();
    $options = stream_context_get_options($context);

    $separ = '+'.str_repeat('-', 73)."\n";
    $result = $separ.$url."\n";

    if ($meta and $meta['uri'] !== $url) {
      $result .= "Stream URL differs: $meta[uri]\n";
    }
    $result .= "$separ\n";

    // Stream type
    if (!$meta) {
      $result .= "Stream metadata is unavailable.\n\n";
    } else {
      $result .= "$meta[wrapper_type] wrapper, $meta[stream_type] stream\n\n";

      if ($filters = &$meta['filters']) {
        $result .= '  Filters: '.join(', ', $filters)."\n";
      }

      $flags = array();
      empty($meta['eof']) or $flags[] = 'At EOF';
      empty($meta['timed_out']) or $flags[] = 'Timed out';
      $flags and $result .= '  State: '.join(', ', $flags)."\n";

      if ($filters or $flags) { $result .= "\n"; }
    }

    // Context options
    if (!$options) {
      $result .= "Stream context options are unavailable\n\n";
    } else {
      $options = reset($options);

      if ($headers = &$options['header']) {
        $result .= "Request:\n\n".static::joinHeaders($headers)."\n\n";
        unset($options['header']);
      }

      if ($version = &$options['protocol_version']) {
        $version = sprintf('%1.1f', $version);
      }

      ksort($options);
      $result .= "Context options:\n\n".static::joinIndent($options)."\n\n";
    }

    // Response
    if ($data = &$meta['wrapper_data']) {
      isset($data['headers']) and $data = $headers['headers'];
      $data and $result .= "Response:\n\n".static::joinHeaders($data)."\n";
    }

    return $result;
  }

  static function joinHeaders(array $list, $indent = '  ') {
    $keyValues = array();

    foreach ($list as $value) {
      if (!is_string($value) or strrchr($value, ':') === false) {
        $keyValues[] = $value;
      } else {
        $keyValues[strtok($value, ':')] = trim(strtok(null));
      }
    }

    return static::joinIndent($keyValues, $indent);
  }

  static function joinIndent(array $keyValues, $indent = '  ') {
    $length = 0;

    foreach ($keyValues as $key => &$value) {
      $length = max($length, strlen($key) + 4);
      $value = is_scalar($value) ? var_export($value, true) : gettype($value);
    }

    return join("\n", S($keyValues, function ($value, $key) use ($indent, $length) {
      return $indent.str_pad("$key:", $length)."$value";
    }));
  }

}
