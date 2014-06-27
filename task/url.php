<?php namespace Sqobot;

class TaskUrl extends Task {
  private $data;
  private $httpCode;
  private $httpMovedURL;
  
  function dwCallback($httpCode, $data, $httpMovedURL) {
      $this->data = $data;
      $this->httpCode = $httpCode;
      $this->httpMovedURL = $httpMovedURL;
  }
  
  function do_dl(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'url dl URL [HEADER[=VALUE] [...]] --to[=out/HOST.html]';
    }

    $all = opt();
    $url = array_shift($all);

    $headers = array();

    foreach ($all as $str) {
      if (strrchr($str, '=') === false) {
        $headers[$str][] = 1;
      } else {
        $headers[strtok($str, '=')] = strtok(null);
      }
    }

    download($url, $headers, array(&$this,'dwCallback'));
    finishDownload();
    
    if ($this->httpCode != 200) {
        echo "Http return code: $this->httpCode",PHP_EOL;
    }
    if ($this->httpMovedURL)
        echo "Redirect: $this->httpMovedURL", PHP_EOL;
    
    if ($to = &$args['to']) {
      is_string($to) or $to = 'out/'.parse_url($url, PHP_URL_HOST).'.html';

      if (!is_int(file_put_contents($to, $this->data))) {
        return print "Cannot write data to [$to].";
      }
    } else {
      echo $data;
    }
  }
}
