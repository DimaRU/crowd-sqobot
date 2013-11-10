<?php namespace Sqobot;

// Scan kickstarter.com new projects
class TaskScan extends Task {
  static function table($table) {
    return cfg('dbPrefix').$table;
  }
  
  private function scan_index($site, $url, $index_table) {
    $options = array('site' => $site, 'table' => $index_table);

    echo "Scanning index for $site", PHP_EOL;

    $started = microtime(true);
    $pages = 0;
    do {
        $pages++;
        $url = Sqissor::process($url, $options);
    } while($url);
    $duration = microtime(true) - $started;
    
    log("Done index scan $site, $pages pages. ".
         sprintf('This took %1.2f sec.', $duration));
    $sql = "SELECT COUNT(1) AS count FROM `$index_table` WHERE 1";
    $stmt = exec($sql);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    log("Total index rows: ".$rows[0]->count);
  }

  private function scan_pages($site, $index_table, $page_table) {
    $options = array('site' => $site);
    
    // Fetch all new projects
    $sql =  "SELECT `$index_table`.project_id, `$index_table`.ref_page ".
            "FROM `$index_table` ".
            "LEFT JOIN `$page_table` ON `$index_table`.project_id = `$page_table`.project_id ".
            "WHERE `$page_table`.project_id IS NULL";
    
    $stmt = exec($sql);
    $projects = $stmt->fetchAll();
    $stmt->closeCursor();
    $pages = count($projects);
    echo "Scanning $pages new project pages.", PHP_EOL;
    $started = microtime(true);
   
    foreach($projects as $project) {
        $options['ref_page'] = $project->ref_page;
        Sqissor::process("http://" . $project->project_id, $options);
    }
    $duration = microtime(true) - $started;
    log("Done pages scan $site, $pages pages. ".
         sprintf('This took %1.2f sec.', $duration));
    
    // Clean up index
    $sql =  "DELETE `$index_table` ".
            "FROM `$index_table` ".
            "LEFT JOIN `$page_table` ON `$index_table`.project_id = `$page_table`.project_id ".
            "WHERE `$page_table`.project_id IS NOT NULL";
    
    $count = exec($sql);
    log("$count pages deleted from index.");
  }
  
  //
  // Scan news from site
  //  
  function do_(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan SITENAME';
    }
    $name = opt(0);

    if (!$scancfg = cfg("scan $name")) return print "No scan $name configuration string".PHP_EOL;
    list($site_index, $site_page, $index_table, $page_table, $start_page) = explode(' ', trim($scancfg));
    $index_table = static::table($index_table);
    $page_table = static::table($page_table);

    try {
      $this->scan_index($site_index, $start_page, $index_table);
    } catch (\Exception $e) {
      echo 'Exception: ', exLine($e), PHP_EOL;
      if (!empty($args['no-ignore'])) { return; }
    }
    
    try {
      $this->scan_pages($site_page, $index_table, $page_table);
    } catch (\Exception $e) {
      echo 'Exception: ', exLine($e), PHP_EOL;
      if (!empty($args['no-ignore'])) { return; }
    }
  }
      
  //
  // Scan one page
  //
  function do_url(array $args = null) {
    if ($args === null or !opt(1)) {
      return print 'scan url SITE URL';
    }
    
    $site = opt(0);
    $url = opt(1);
    $options = array('site' => $site);
    echo "Process url $url", PHP_EOL;
    $url = Sqissor::process($url, $options);
    if ($url) {
        echo "Returned $url", PHP_EOL;
    }
  }
    
  // Scan index
  function do_index(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan index SITE URL table-index';
    }

    $site = opt(0);
    $url = opt(1);
    $index_table = cfg('dbPrefix').opt(2);
    $this->scan_index($site, $url, $index_table);
  }
  
  // Scan missing pages from index
  function do_pages(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan pages SITE INDEX-TABLE PAGE-TABLE';
    }

    $site = opt(0);
    $index_table = cfg('dbPrefix').opt(1);
    $page_table = cfg('dbPrefix').opt(2);
    $this->scan_pages($site, $index_table, $page_table);
  }
  
}