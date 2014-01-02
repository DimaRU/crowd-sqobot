<?php namespace Sqobot;

// Scan kickstarter.com new projects
class TaskScan extends Task {
  static function table($table) {
    return cfg('dbPrefix').$table;
  }
  
  private function scan_index($site_id, $url, $index_table, $maxpage = null) {
    echo "Scanning index for $site_id", PHP_EOL;

    $started = microtime(true);

    $options = array('index_table' => $index_table);
    $pages = 0;
    do {
        $pages++;
        $url = Sqissor::process($url, $site_id.".Index", $options);
        if ($maxpage and $pages >= $maxpage) {
            break;
        }
    } while($url);
    $duration = microtime(true) - $started;
    
    log("Done index scan $site_id, $pages pages. ".
         sprintf('This took %1.2f sec.', $duration));
    $sql = "SELECT COUNT(1) AS count FROM `$index_table` WHERE `site_id` = \"$site_id\"";
    $stmt = exec($sql);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    log("Total index rows: ".$rows[0]->count);
  }

  private function scan_pages($site_id, $index_table, $page_table) {
    // Fetch all new projects
    $sql =  "SELECT `$index_table`.project_id, `$index_table`.ref_page ".
            "FROM `$index_table` ".
            "LEFT JOIN `$page_table` ON `$index_table`.project_id = `$page_table`.project_id ".
            "WHERE (`$page_table`.project_id IS NULL) AND (`$index_table`.site_id = \"$site_id\")";
    
    $stmt = exec($sql);
    $projects = $stmt->fetchAll();
    $stmt->closeCursor();
    $pages = count($projects);
    echo "Scanning $pages new project pages.", PHP_EOL;
    $started = microtime(true);

    $options = array('page_table' => $page_table);
    foreach($projects as $project) {
        $options['ref_page'] = $project->ref_page;
        try {
            Sqissor::process("http://" . $project->project_id, $site_id.".Page", $options);
        } catch (\Exception $e) {
            echo 'Exception: ', exLine($e), PHP_EOL;
        }
    }
    $duration = microtime(true) - $started;
    log("Done pages scan $site_id, $pages pages. ".
         sprintf('This took %1.2f sec.', $duration));
    
    // Clean up index
    // TODO: save bad pages for later analyses
    $sql =  "DELETE `$index_table` ".
            "FROM `$index_table` ".
            "WHERE `$index_table`.site_id = \"$site_id\"";

    $count = exec($sql);
    log("$count pages deleted from index.");
  }
  
  private function scan_stats($site_id, $index_table, $stats_table) {
      
  }
  //
  // Scan news from site
  //
  function do_new(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan new SITENAME --maxpage=num';
    }
    $site_id = opt(0);

    if (!$scancfg = cfg("scan $site_id")) return print "No scan $site_id configuration string".PHP_EOL;
    list($index_table, $page_table, $stats_table, $start_page) = explode(' ', trim($scancfg));
    $index_table = static::table($index_table);
    $page_table = static::table($page_table);
    $stats_table = static::table($stats_table);
    $maxpage = isset($args['maxpage']) ? $args['maxpage'] : null ;

    try {
      $this->scan_index($site_id, $start_page, $index_table, $maxpage);
    } catch (\Exception $e) {
      echo 'Exception: ', exLine($e), PHP_EOL;
      if (!empty($args['no-ignore'])) { return; }
    }
   
    $this->scan_pages($site_id, $index_table, $page_table);
  }

  //
  // Scan single page
  //
  function do_url(array $args = null) {
    if ($args === null or !opt(1)) {
      return print 'scan url SITE URL [OPTION=[VALUE] [...]]';
    }

    $all = opt();
    $site = array_shift($all);
    $url = array_shift($all);

    $options = array();

    foreach ($all as $str) {
      if (strrchr($str, '=') === false) {
        $options[$str][] = 1;
      } else {
        $options[strtok($str, '=')] = strtok(null);
      }
    }
    
    echo "Process url $url", PHP_EOL;
    $url = Sqissor::process($url, $site, $options);
    if ($url) {
        echo "Returned $url", PHP_EOL;
    }
  }
    
  // Scan index
  function do_index(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan index SITENAME URL PAGE-TABLE --maxpage=num';
    }

    $site_id = opt(0);
    $url = opt(1);
    $index_table = cfg('dbPrefix').opt(2);
    $maxpage = isset($args['maxpage']) ? $args['maxpage'] : null ;

    $this->scan_index($site_id, $url, $index_table, $maxpage);
  }
  
  // Scan missing pages from index
  function do_pages(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan pages SITENAME INDEX-TABLE PAGE-TABLE';
    }

    $site_id = opt(0);
    $index_table = cfg('dbPrefix').opt(1);
    $page_table = cfg('dbPrefix').opt(2);
    $this->scan_pages($site_id, $index_table, $page_table);
  }

  // Scan updates for stats
  function do_stats(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan stats SITENAME';
    }
    $site_id = opt(0);

    if (!$scancfg = cfg("scan $site_id")) return print "No scan $site_id configuration string".PHP_EOL;
    list($index_table, $page_table, $stats_table, $start_page) = explode(' ', trim($scancfg));
    $index_table = static::table($index_table);
    $page_table = static::table($page_table);
    $stats_table = static::table($stats_table);
    $maxpage = isset($args['maxpage']) ? $args['maxpage'] : null ;

    $this->scan_stats($site_id, $index_table, $stats_table);
  }
  
}