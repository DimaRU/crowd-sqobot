<?php namespace Sqobot;

abstract class Sqissor {
  public $name;         // Real sqissor class name

  public $url;
  public $options;
  static $domain_name;
  static $accept = null;       // Accept: header
  
  // DOMDocument processing
  public $finder;
  public $htmldom;
          
  // Process URL, return next URL, if any
  // Occurred exceptions are logged and re-thrown.
  // return mixed $callback's result
  static function process($url, $site, array $options = array()) {
    $self = get_called_class();
    return rescue(
      // Main function
      function () use ($url, $site, $options, $self) {
        $result = $self::factory($site, $options)->sliceURL($url);
        return $result;
      },
      // Executed hen some error thrown
      function ($e) use ($url, $options, $self) {
        error("$self::process() has failed on url {$url} : ".exLine($e));
      }
    );
  }

  static function factory($site, array $options = null) {
    $class = cfg("class $site", NS.'$');

    if (!$class) {
      $class = NS.'S'.preg_replace('/\.+(.)/e', 'strtoupper("\\1")', ".$site");
    }

    if (class_exists($class)) {
      return new $class($options);
    } else {
      throw new ENoSqissor("Undefined Sqissor class [$class] of site [$site].".
                           "You can list custom class under 'class $site=YourClass'".
                           "line in any of Sqobot's *.conf.");
    }
  }

  static function siteNameFrom($class) {
    is_object($class) and $class = get_class($class);

    if (S::unprefix($class, NS.'S')) {
      return strtolower( trim(preg_replace('/[A-Z]/', '.\\0', $class), '.') );
    } else {
      return $class;
    }
  }

  static function make(array $options = null) {
    return new static($options);
  }

  function __construct(array $options = null) {
    $this->name = static::siteNameFrom($this);
    $this->options = $options;
  }

  function sliceURL($url) {
    $this->url = $url;
    log("Process {$this->name} $url", 'debug');
    $referer = dirname($url);
    strrchr($referer, '/') === false and $referer = null;
    return $this->slice(download($url, array('referer'  => $referer, 'accept' => static::$accept)));
  }

  //
  // Processes given $data string. 'Processing' means that it's parsed and new
  // URLs are ->enqueue()'d, Pool entries created and so on. The actual purpose
  // of the robot.
  //
  //* $data str   - typically is a fetched URL content unless ->sliceURL() is
  //  overriden in a child class.
  //* $transaction  null read value from ->$transaction
  //                bool whether to wrap processing in a database transaction or not
  //
  //? slice('<!DOCTYPE html><html>...</html>')
  //
  function slice($data) {
    return $this->doSlice($data, $this->options);
  }

  //
  // Overridable method that contains the actual page parsing logics.
  //
  //* $data str     - value given to ->slice() - typically fetched URL (HTML code).
  //* $extra array  - associated with this queue item (see ->enqueue()). The same
  //  value can be accessed by ->extra().
  //
  //= mixed return value is ignored
  //
  //? doSlice('<!DOCTYPE html><html>...</html>', array('a' => 'b'))
  //
  protected abstract function doSlice($data, array $options);

  //
  // Return associated domain name;
  //
  function domain() {
      return static::$domain_name;
  }
  
  //
  // Returns extra data associated with this item.
  // Empty array is returned if no extra was assigned.
  //
  //= array
  //
  function extra() {
    return $this->options;
  }

  //
  // Matches given $regexp against string $str optionally returning pocket of given
  // index $return. If $return is null all pockets are returned. If not an error
  // occurs. Same happens if preg_last_error() wasn't 0 (e.g. for non-UTF-8 string
  // when matching with /u (PCRE8) modifier.
  //
  //* $str str    - input string to match $regexp against like 'To MATCH 123, foo.'.
  //* $regexp str - full regular expression like '/match (\d+)/i'.
  //* $return null  get all matches,
  //          true  get all matches but fail if $regexp has failed,
  //          int   pocket index to return
  //
  //= null    if $return is null and none matched or if there's no match with
  //          $return index.
  //= array   if $return is null.
  //= str     given match by index.
  //
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i')
  //      //=> array('MATCH 123', '123')
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i', 1)
  //      //=> '123'
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i', 4444)
  //      //=> null
  //? regexp('foo!', '/bar/')
  //      //=> null
  //? regexp('foo!', '/bar/')
  //      // exception occurs
  //? regexp('foo!', '/bar/', 1)
  //      // exception occurs
  //
  function regexp($str, $regexp, $return = null) {
    if (preg_match($regexp, $str, $match)) {
      if (isset($return) and $return !== true) {
        return S::pickFlat($match, $return);
      } else {
        return $match;
      }
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError($this, "Regexp error code #$error for $regexp");
    } elseif (isset($return)) {
      throw new ERegExpNoMatch($this, "Regexp $regexp didn't match anything.");
    }
  }

  //
  // Takes all matches of given $regexp against string $str. $flags specify
  // preg_match_all() flags; if boolean true is set to PREG_SET_ORDER. Errors if
  // no matches were found. Checks for preg_last_error() as well as ->regexp() does.
  //
  //* $str str    - input string to match $regexp against like 'A 123, b 456.'.
  //* $regexp str - full regular expression like '/[a-z] (\d+)/i'.
  //* $flags int given to preg_match_all(), true set to PREG_SET_ORDER
  //
  //= array   all matches of $regexp that were found in $str.
  //
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i')
  //      //=> array(array('A 123', 'b 456'), array('123', '456'))
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i', true)
  //      //=> array(array('A 123', '123'), array('b 456', '456'))
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i', PREG_SET_ORDER)
  //      // equivalent to the above
  //? regexpAll('...', '...', PREG_SET_ORDER | PREG_OFFSET_CAPTURE)
  //      // calls preg_match_all() with these two flags and returns
  //      // an array with pockets and their offsets in original string
  //
  function regexpAll($str, $regexp, $flags = 0) {
    $flags === true and $flags = PREG_SET_ORDER;

    if (preg_match_all($regexp, $str, $matches, $flags)) {
      return $matches;
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError($this, "Regexp error code #$error for $regexp");
    } else {
      throw new ERegExpNoMatch($this, "Regexp $regexp didn't match anything.");
    }
  }

  //
  // Creates associative array from string $str based on given $regexp using
  // pocket with index $keyIndex as resulting array's keys and $valueIndex
  // as its value (if null uses entire match as a value).
  // Errors if no matches were found or on preg_last_error() (see ->regexpAll()).
  //
  //* $str str    - input string to match $regexp against like 'A 123, b 456.'.
  //* $regexp str - full regular expression like '/[a-z] (\d+)/i'.
  //* $keyIndex int - pocket index to set array's keys from; if there were
  //  identical keys earlier occurrences are overriden by latter.
  //* $valueIndex null  set array's value to the entire match,
  //              int   set it to this pocket index's value or null if none
  //
  //= hash
  //
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1)
  //      //=> array('A' => array('A', '123'), 'b' => array('b', '456'))
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 1)
  //      //=> array('A' => '123', 'b' => '456')
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 0)
  //      //=> array('A' => 'A', 'b' => 'b')
  //? regexpMap( 'A 1, A 2.', '/[a-z] (\d+)/i', 1)
  //      //=> array('A' => array('A', '2'))
  //? regexpMap( 'A 1, a 2.', '/[a-z] (\d+)/i', 1, 1)
  //      //=> array('A' => '1', 'a' => '2')
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 4444)
  //      //=> array('A' => null, 'b' => null)
  //
  function regexpMap($str, $regexp, $keyIndex, $valueIndex = null) {
    return S::keys(
      $this->regexpAll($str, $regexp, true),
      function ($match) use ($keyIndex, $valueIndex) {
        if (isset($valueIndex)) {
          $value = &$match[$valueIndex];
          return array($match[$keyIndex], $value);
        } else {
          return $match[$keyIndex];
        }
      }
    );
  }

  //
  // Creates an associative array of pages index => URL by matching $pageRegexp
  // capturing a single page against string $str. If $onlyAfter is set removes
  // pages with indexes prior to this value from result (useful to avoid going
  // backwards and crawling already processed pages).
  // Errors if no matches were found or on preg_last_error() (see ->regexpAll()).
  //
  //* $str str    - input string containing list of pages (e.g. HTML).
  //* $pageRegexp str - regular expression matching one page entry with URL as
  //  first pocket and page number - as second. Alternatively it may use named
  //  pockets instead of indexes like '(?P<page>\d+)' and/or '(?P<url>[\w:/]+)'.
  //* $onlyAfter int  - removes page numbers prior to this number from result.
  //
  //= hash int pageNumber => 'url'
  //
  //? matchPages('<a href="page2.html">3</a><a href="page3.html">4</a>...',
  //             '/href="([^"]+)">(\d+)/')
  //      //=> array(3 => 'page2.html', 4 => 'page3.html', ...)
  //? matchPages('...as above...', '...', 3)
  //      //=> array(4 => 'page3.html', ...)
  //? matchPages('...as above...', '...', 4444)
  //      //=> array()
  //? matchPages('foo!', '...')
  //      // exception occurs
  //? matchPages('<b>3</b>: page2.html | <b>4</b>: page3.html | ...',
  //             '/<b>(?P<page>\d+)<\/b>\s*(?P<url>[\w.]+)/')
  //      //=> array(3 => 'page2.html', 4 => 'page3.html', ...)
  //
  function matchPages($str, $pageRegexp, $onlyAfter = 0) {
    $links = $this->regexpAll($str, $pageRegexp, true);

    $pages = S::keys($links, function ($match) {
      $page = empty($match['page']) ? $match[2] : $match['page'];
      $url = empty($match['url']) ? $match[1] : $match['url'];
      return array($page, $url);
    });

    $onlyAfter and $pages = S::keep($pages, '#? > '.((int) $onlyAfter));
    return $pages;
  }

  //
  // Removes HTML tags and decodes entities in $html string producing its plain version.
  //
  //* $html str     - like 'See <em>this</em> &amp; <em>that</em>.'.
  //* $charset str  - encoding name $html is in like 'cp1251'.
  //
  //= str     plain text version of $html
  //
  //? htmlToText('See <em>this</em> &amp; <em>that</em>.')
  //      //=> 'See this & that.'
  //? htmlToText('Decodes all &#039; apostrophes and &quot; quotes too.')
  //      //=> 'Decodes all \' apostrophes and " quotes too.'
  //
  function htmlToText($html, $charset = 'utf-8') {
    return html_entity_decode(strip_tags($html), ENT_QUOTES, $charset);
  }

  // Init DOMDocument processing
  function initDom($data) {
    $this->htmldom = new \DOMDocument();
    //$this->htmldom->validateOnParse = true;
    $this->htmldom->recover = true;
    $this->htmldom->strictErrorChecking = false;
    $cur = libxml_use_internal_errors(true);
    $this->htmldom->loadHTML($data);
    libxml_use_internal_errors($cur);
    $this->finder = new \DomXPath($this->htmldom);
  }
  
  function querySafe($expression) {
      $nodes = $this->finder->query($expression);
      if ($nodes->length == 0) {
          throw new ESqissor($this, "Not found expression '$expression'.");
      }
      return $nodes;
  }
  
  function queryAttribute($expression, $attribute) {
    $node = $this->querySafe($expression)->item(0);
    if (!$node->hasAttribute($attribute)) {
        throw new ESqissor($this, "Not found attibyte '$attribute' in '$expression'.");
    }
    return $node->getAttribute($attribute);
  }
  
  function queryValue($expression) {
    $nodes = $this->querySafe($expression);
    return $nodes->item(0)->nodeValue;
  }
  
  function incrementPageNum($url, $pagesym) {
    $parts1 = explode("?", $url);
    $parts2 = explode("&", $parts1[1]);
    //var_dump($parts2);
    foreach($parts2 as &$key) {
       $parts3 = explode("=", $key);
       if ($parts3[0] == $pagesym) {
            $parts3[1] += 1;
            $key = implode("=", $parts3);
       }
    }
    $parts1[1] = implode("&", $parts2);
    return implode("?", $parts1);
  }
}  