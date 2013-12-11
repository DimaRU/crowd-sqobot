<?php namespace Sqobot;

class Download {
  static $maxFetchSize = 20971520;      // 20 MiB
  static $curl;                         // Curl handle. Do not close!!!

  public $contextOptions = array();
  public $url;
  public $headers;

  public $responseHeaders = array();
  public $reply;

  static function it($url, $headers = array()) {
    return static::make()->setContext($url, $headers)->read()->reply;
  }

  static function make() {
    return new static();
  }

  function __construct() {
    if (!Download::$curl) {
        Download::$curl = curl_init();
    }
    if (!Download::$curl) {
          throw new RuntimeException("Cannot init curl library.");
    }
    $this->contextOptions = array(
        CURLOPT_RETURNTRANSFER => true,                             // return web page
        CURLOPT_HEADER         => false,                            // don't return headers
        CURLOPT_FOLLOWLOCATION => cfg('dlRedirects') > 0,           // follow redirects
        CURLOPT_MAXREDIRS      => max(0, (int) cfg('dlRedirects')), // stop after 10 redirects
        CURLOPT_FAILONERROR    => !!cfg('dlFetchOnError'),
        CURLOPT_AUTOREFERER    => true,                             // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,                              // timeout on connect
        CURLOPT_TIMEOUT        => (float) cfg('dlTimeout'),         // timeout on response
        CURLOPT_SSL_VERIFYHOST => false,                            // don't verify ssl
        CURLOPT_SSL_VERIFYPEER => false,                            //
        CURLOPT_VERBOSE        => true,                             //
        CURLOPT_ENCODING       => "",                               // Accept all encoding
        CURLINFO_HEADER_OUT    => true,                             // Report req headers in info
        CURLOPT_HEADERFUNCTION => array($this, '_set_header_callback')   //return the HTTP Response header using the callback function readHeader
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

  function setContext($url, $headers = array()) {
    $this->url($url);
    is_array($headers) or $headers = array('referer' => $headers);
    $this->headers = array_change_key_case($headers);
    curl_setopt_array(Download::$curl, $this->contextOptions);
    curl_setopt(Download::$curl, CURLOPT_HTTPHEADER, $this->normalizeHeaders());
    curl_setopt(Download::$curl, CURLOPT_URL, $this->url);
    return $this;
  }

  function read() {
    //$limit = min(static::$maxFetchSize, PHP_INT_MAX);
    $this->reply = curl_exec(Download::$curl);
    $this->write_log();
    if ($this->reply === false) {
      throw new RuntimeException("Error '".curl_error(Download::$curl)."' loading [{$this->url}].");
    }
    return $this;
  }

  function close() {
    $h = Download::$curl and curl_close($h);
    Download::$curl = null;
    return $this;
  }
  
  private function _set_header_callback($ch, $header) {
        $this->responseHeaders[] = $header;
        return strlen($header);
  } 

  //* $context resource of stream_context_create()
  //* $file resource of fopen(), false if failed
  protected function write_log() {
    if ($log = static::logFile()) {
      if (is_file($log) and filesize($log) >= S::size(cfg('dlLogMax'))) {
        file_put_contents($log, '', LOCK_EX);
      }

      S::mkdirOf($log);
      $info = static::summarize($this->url);
      $ok = file_put_contents($log, "$info\n\n", LOCK_EX | FILE_APPEND);
      $ok or warn("Cannot write to dlLog file [$log].");
    }
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

  function header_accept_encoding($str = '') {
    return cfg('dl encoding');
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
  function summarize($url) {
    $meta = curl_getinfo(Download::$curl);

    $separ = '+'.str_repeat('-', 73)."\n";
    $result = $separ.$url."\n";

    if ($meta and $meta['url'] !== $url) {
      $result .= "Stream URL differs: $meta[url]\n";
    }

    $result .= "$separ\n";
    
    // Request
    if (isset($meta['request_header'])) {
        $headers = explode("\r\n", $meta['request_header']);
        unset($headers[0]);
        $result .= "Request:\n\n".static::joinHeaders($headers)."\n\n";
    }

    // TODO: stats
    $stats = array_intersect_key(
        $meta, 
        array_flip(array(
            'http_code',
            'total_time', 
            'namelookup_time', 
            'connect_time',
            'starttransfer_time'))
    );
    
    $result .= "Stats:\n\n".static::joinIndent($stats)."\n\n";

    // Response
    $this->responseHeaders and $result .= "Response:\n\n".static::joinHeaders($this->responseHeaders)."\n";

    return $result;
  }

  static function joinHeaders(array $list, $indent = '  ') {
    $keyValues = array();

    foreach ($list as $value) {
      $v = trim($value);
      if ($v == "")          continue;
      if (!is_string($v) or strrchr($v, ':') === false) {
        $keyValues[] = $v;
      } else {
        $key = strtok($v, ':');
        isset($keyValues[$key]) and $key .= " ";
        $keyValues[$key] = trim(strtok(null));
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
      return $indent.str_pad(trim($key).":", $length)."$value";
    }));
  }

}
