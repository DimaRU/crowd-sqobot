<?php namespace Sqobot;

class Download {
  static $requests = 0;                 // Total download requests, for stats
  static $timeouts = 0;                 // Timeouts, for stats
  static $toomany = 0;                  // Too many Requests errors, for stats
  static $starttransfer_times = 0;      // Total starttransfer_time
  static $size_download = 0;            // Total downloaded bytes
  
  static $maxRequests;                  // Max number of parallel requests
  static $curl_mh;                      // Curl_multi handle
  static $curl_array = array();         // Array of curl handles
  static $outstanding_requests = array();           // Array of download objects
  static $stdContextOptions;            // Standart context options

  private $curl;                        // Curl handle. Do not close!!!
  private $url;                         // Current loaded url
  private $headers;                     // Download headers
  private $callback;                    // Callback function
  private $responseHeaders = array();   // Response headers
  private $retry = 0;                   // Count of request retry
 
  // Return Download object
  static function start($url, array $headers = array(), $callback = null) {
    static::make()->setContext($url, $callback, $headers)->startRequest();
  }

  static function make() {
    return new static();
  }

  function __construct() {
      $this->curl = self::getFree_curl();
  }

  function __destruct() {
  }

  // Initialise curl_multi
  static function init() {
      self::$curl_mh = curl_multi_init();
      self::$maxRequests = cfg('maxRequests');
      
      for($i = 0; $i < self::$maxRequests; $i++) {
        if (!(self::$curl_array[$i] = curl_init())) {
          throw new RuntimeException("Cannot init curl library.");
        }
      }
      
      self::$stdContextOptions = array(
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
        CURLOPT_VERBOSE        => false,                            //
        CURLOPT_ENCODING       => "",                               // Accept all encoding
        CURLINFO_HEADER_OUT    => true,                             // Report req headers in info
        CURLOPT_COOKIESESSION  => true,
        CURLOPT_COOKIEFILE     => "",
        CURLOPT_COOKIEJAR      => "",
    );
  }
  
  private function _set_header_callback($ch, $header) {
      $this->responseHeaders[] = $header;
      return strlen($header);
  }
 
  static function getFree_curl() {
      while (true) {
        for($i = 0; $i < self::$maxRequests; $i++) {
          if (!isset(self::$outstanding_requests[(int)self::$curl_array[$i]])) {
              return self::$curl_array[$i];
          }
        }
        self::waitExecute();
      }
  }

  static function finishAllRequests() {
      while (count(self::$outstanding_requests)) {
          self::waitExecute();
      }
  }

  // Wait for any cequests complete
  static function waitExecute() {
        // None of free slots, wait for complete request
        curl_multi_select(self::$curl_mh);
        // Execute all completed
        self::executeCompleted();
        
        
	/*
        // Call select to see if anything is waiting for us
        if (curl_multi_select($this->multi_handle, 0.0) === -1)
            return;
        
        // Since something's waiting, give curl a chance to process it
        do {
            $mrc = curl_multi_exec($this->multi_handle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        */
        // fix for https://bugs.php.net/bug.php?id=63411
	do {
            $mrc = curl_multi_exec(self::$curl_mh, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select(self::$curl_mh) != -1) {
                do {
                    $mrc = curl_multi_exec(self::$curl_mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            } else {
                return;
            }
        }        
        
  }
  
  private function startRequest() {
      self::$outstanding_requests[(int)$this->curl] = $this;
      self::$requests++;
      remoteDelay();        // Sleep beetwen new requests
      curl_multi_add_handle(self::$curl_mh, $this->curl);
      // Start execution
      do {
          $mrc = curl_multi_exec(self::$curl_mh, $active);
      } while ($mrc == CURLM_CALL_MULTI_PERFORM);      
      if ($mrc != CURLM_OK) {
        throw new \RuntimeException("Error '".curl_multi_strerror($mrc)."' while loading [{$this->url}].");
      }
      return $this;
  }

  static function executeCompleted() {
      // Now grab the information about the completed requests
      while ($info = curl_multi_info_read(self::$curl_mh)) {
        $ch = $info['handle'];
        $ch_array_key = (int)$ch;
        
        if (!isset(self::$outstanding_requests[$ch_array_key])) {
                die("Error - handle wasn't found in requests: '$ch' in ".
                    print_r(self::$outstanding_requests, true));
            }
            
        $request = self::$outstanding_requests[$ch_array_key];
        // Mark as free for use
        unset(self::$outstanding_requests[$ch_array_key]);
        curl_multi_remove_handle(self::$curl_mh, $ch);
        $request->execute();
      }
    
  }
  
  private function setContext($url, $callback, array $headers) {
    $this->url(realURL($url));
    $this->callback = $callback;
    is_array($headers) or $headers = array('referer' => $headers);
    $this->headers = array_change_key_case($headers);
    curl_setopt_array($this->curl, self::$stdContextOptions);
    curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, array($this, '_set_header_callback'));   //return the HTTP Response header using the callback function readHeader
    curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->normalizeHeaders());
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    return $this;
  }

  private function execute() {
    $this->write_log();
    $errno = curl_errno($this->curl);
    $meta = curl_getinfo($this->curl);

    if (isset($meta['starttransfer_time']))
        self::$starttransfer_times += $meta['starttransfer_time']; 
    if (isset($meta['size_download']))
        self::$size_download += $meta['size_download']; 
    
    // if ($errno == CURLE_OPERATION_TIMEOUTED) { // Does not work in multi-curl
    if (isset($meta['starttransfer_time']) && $meta['starttransfer_time'] == 0) {
        self::$timeouts++;
        $this->retry++;
        warn("Download timeout, retry $this->retry. $this->url");
        if ($this->retry < cfg('dlRetry'))
            return $this->startRequest();     // Retry request
    }
    
    if ($this->httpCode() == 429) {
        self::$toomany++;           // Too many requests
        $retryAfter = $this->httpRetryAfter();
        warn("Return http code: 429 Too many requests, retry afler $retryAfter ". $this->url);
        log("Content: " . $this->getContent(), 'debug');
        if ($retryAfter == FALSE) {
            throw new \RuntimeException("Error: no Retry_Afler header. [{$this->url}].");
        }
        remoteDelay(null, $retryAfter * 1000);
        return $this->startRequest();     // Retry request
    }

    switch($errno) {
      case CURLE_OK:
          if (in_array($this->httpCode(), array(0,200))) {
            call_user_func($this->callback, $this->httpCode(), $this->getContent(), $this->httpMovedURL());
            return $this;
          }
      case CURLE_HTTP_NOT_FOUND:
          log("Return http code:".$this->httpCode()." ".$this->url, ($this->httpCode() == 404) ? 'warn':'error');
          if ($this->httpCode() == 404) {
            call_user_func($this->callback, $this->httpCode(), $this->getContent(), $this->httpMovedURL());
            return $this;
          }
      default :
        throw new \RuntimeException("Error '".curl_error($this->curl)."' loading [{$this->url}].");
    }
  }

  private function getContent() {
      return curl_multi_getcontent($this->curl);
  }
  
  private function httpCode() {
    return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
  }
  
  private function httpMovedURL() {
      if (array_search("Status: 301 Moved Permanently", $this->responseHeaders) !== NULL) {
          foreach ($this->responseHeaders as $hdr) {
              if (strpos($hdr, "Location:") !== false) {
                  return trim(str_replace("Location:", "", $hdr));
              }
          }
      }
      return false;
  }
  
  // Return Retry-After header value
  private function httpRetryAfter() {
          foreach ($this->responseHeaders as $hdr) {
              if (strpos($hdr, "Retry-After:") !== false) {
                  return trim(str_replace("Retry-After:", "", $hdr));
              }
          }
      return false;
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
    
  //* $context resource of stream_context_create()
  //* $file resource of fopen(), false if failed
  protected function write_log() {
    if (strpos(cfg('log', ' $ '), ' debug ') !== false or $this->httpCode() != 200) {
        if ($log = static::logFile()) {
          if (is_file($log) and filesize($log) >= S::size(cfg('dlLogMax'))) {
            file_put_contents($log, '', LOCK_EX);   // truncate file
          }

          S::mkdirOf($log);
          $info = $this->summarize($this->url);
          $ok = file_put_contents($log, "$info\n\n", LOCK_EX | FILE_APPEND);
          $ok or warn("Cannot write to dlLog file [$log].");
        }
    }
  }


  //= array of scalar like 'Accept: text/html'
  private function normalizeHeaders() {
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

  private function header_accept_language() {
    return cfg('dl languages');
  }

  private function header_accept_charset() {
    return cfg('dl charsets');
  }

  private function header_accept_encoding() {
    return cfg('dl encoding');
  }

  private function header_accept() {
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

  // Create log record
  private function summarize($url) {
    $meta = curl_getinfo($this->curl);

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
