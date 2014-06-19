<?php namespace Sqobot;

class Download {
  static $maxFetchSize = 20971520;      // 20 MiB
  static $curl;                         // Curl handle. Do not close!!!
  static $requests = 0;                 // Total download requests, for stats
  static $timeouts = 0;                 // Timeouts, for stats
  static $starttransfer_times = 0;      // Total starttransfer_time

  private $contextOptions = array();
  private $url;
  private $headers;
  private $callback;

  static $responseHeaders;

  // Return Download object
  static function start($url, array $headers = array(), $callback = null) {
    $dw = static::make()->setContext($url, $callback, $headers)->read();
    call_user_func($callback, $dw);
  }

  static function make() {
    return new static();
  }

  function __construct() {
    static::$responseHeaders = array();
    if (!self::$curl) {
        self::$curl = curl_init();
    }
    if (!self::$curl) {
          throw new RuntimeException("Cannot init curl library.");
    }
    $this->contextOptions = array(
        CURLOPT_RETURNTRANSFER => true,                             // return web page
        CURLOPT_HEADER         => false,                            // don't return headers
        CURLOPT_FOLLOWLOCATION => cfg('dlRedirects') > 0,           // follow redirects
        CURLOPT_MAXREDIRS      => max(0, (int) cfg('dlRedirects')), // stop after 10 redirects
        CURLOPT_FAILONERROR    => !cfg('dlFetchOnError'),
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
    //$this->close();
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

  function setContext($url, $callback, array $headers = array()) {
    $this->url(realURL($url));
    $this->callback = $callback;
    is_array($headers) or $headers = array('referer' => $headers);
    $this->headers = array_change_key_case($headers);
    curl_setopt_array(self::$curl, $this->contextOptions);
    curl_setopt(self::$curl, CURLOPT_HTTPHEADER, $this->normalizeHeaders());
    curl_setopt(self::$curl, CURLOPT_URL, $this->url);
    return $this;
  }

  function read() {
    //$limit = min(static::$maxFetchSize, PHP_INT_MAX);
     for($retry = 0; $retry < cfg('dlRetry'); $retry++) {
        curl_exec(static::$curl);
        self::$requests++;
        $this->write_log();
        if (($errno = curl_errno(static::$curl)) != CURLE_OPERATION_TIMEOUTED) {
            break;
        }
        self::$timeouts++;
     } 
    
    switch($errno) {
      case CURLE_OK:
          if (in_array(static::httpReturnCode(), array(0,200))) {
            return $this;
          }
      case CURLE_HTTP_NOT_FOUND:
          log("Return http code:".static::httpReturnCode()." ".$this->url, (static::httpReturnCode() == 404) ? 'warn':'error');
          if (static::httpReturnCode() == 404) {
             return $this;
          }
      default :
        throw new \RuntimeException("Error '".curl_error(static::$curl)."' loading [{$this->url}].");
    }
  }

  public function getContent() {
      return curl_multi_getcontent(static::$curl);
  }
    
  function close() {
    $h = self::$curl and curl_close($h);
    self::$curl = null;
    return $this;
  }
  
  private function _set_header_callback($ch, $header) {
        static::$responseHeaders[] = $header;
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

  private function has($header) {
    return isset($this->headers[$header]);
  }

  private function header_accept_language($str = '') {
    return cfg('dl languages');
  }

  private function header_accept_charset($str = '') {
    return cfg('dl charsets');
  }

  private function header_accept_encoding($str = '') {
    return cfg('dl encoding');
  }

  private function header_accept($str = '') {
    return cfg('dl mimes');
  }

  private function header_user_agent() {
    return cfg('dl useragent');
  }

  private function header_cache_control() {
    return cfg('dl cache');
  }

  private function header_referer() {
    return 'http://'.parse_url($this->url, PHP_URL_HOST).'/';
  }
  
  static function logFile() {
    return strftime( opt('dlLog', cfg('dlLog')) );
  }

  public function httpReturnCode() {
    return curl_getinfo(self::$curl, CURLINFO_HTTP_CODE);
  }
  public function httpMovedURL() {
      if (array_search("Status: 301 Moved Permanently", static::$responseHeaders) !== NULL) {
          foreach (static::$responseHeaders as $hdr) {
              if (strpos($hdr, "Location:") !== false) {
                  return trim(str_replace("Location:", "", $hdr));
              }
          }
      }
      return false;
  }
  
  // Create log record
  private function summarize($url) {
    $meta = curl_getinfo(self::$curl);

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
    if (isset($meta['starttransfer_time'])) {
        self::$starttransfer_times += $meta['starttransfer_time']; 
    }
    $stats = array_intersect_key(
        $meta, 
        array_flip(array(
            'http_code',
            'size_download',
            'total_time', 
            'namelookup_time', 
            'connect_time',
            'starttransfer_time'))
    );
    
    $result .= "Stats:\n\n".static::joinIndent($stats)."\n\n";

    // Response
    static::$responseHeaders and $result .= "Response:\n\n".static::joinHeaders(static::$responseHeaders)."\n";

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
