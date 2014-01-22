<?php namespace Sqobot;

// Scan kickstarter.com new projects
class TaskScan extends Task {
  static function table($table) {
    return cfg('dbPrefix').$table;
  }
  
  public function getSiteOptions($site_id) {
    if (!$scancfg = cfg("scan $site_id")) die("No scan $site_id configuration string");

    list($index_table, $page_table, $stats_table, $start_page) = explode(' ', trim($scancfg));
    $index_table = static::table($index_table);
    $page_table = static::table($page_table);
    $stats_table = static::table($stats_table);
    $x = compact('index_table', 'page_table', 'stats_table');
    return $x;
  }
  
  public function getSiteStartURL($site_id) {
    if (!$scancfg = cfg("scan $site_id")) die("No scan $site_id configuration string");
    list($index_table, $page_table, $stats_table, $start_page) = explode(' ', trim($scancfg));
    return $start_page;
  }
  
  private function scan_index($site_id, $url, $options, $maxpage = null) {
    echo "Scanning index for $site_id", PHP_EOL;
    extract($options);

    $pages = 0;
    do {
        $pages++;
        $url = Sqissor::process($url, $site_id.".Index", $options);
        if ($maxpage and $pages >= $maxpage) {
            break;
        }
    } while($url);
    
    $sql = "SELECT COUNT(1) AS count FROM `$index_table` WHERE `site_id` = \"$site_id\"";
    $stmt = exec($sql);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
    log("Done index scan $site_id, $pages pages. Total index rows: ".$rows[0]->count);
  }

  private function scan_pages($site_id, $options) {
    extract($options);
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

    foreach($projects as $project) {
        $options['ref_page'] = $project->ref_page;
        try {
            Sqissor::process("http://" . $project->project_id, $site_id.".Page", $options);
        } catch (\Exception $e) {
            echo 'Exception: ', exLine($e), PHP_EOL;
        }
    }
    
    // Clean up index
    // Delete only scanned
    $sql =  "DELETE `$index_table` ".
            "FROM `$index_table` ".
            "LEFT JOIN `$page_table` ON `$index_table`.project_id = `$page_table`.project_id ".
            "WHERE (`$page_table`.project_id IS NOT NULL) AND (`$index_table`.site_id = \"$site_id\")";
    
    // Clean up index, delete all
    //$sql =  "DELETE `$index_table` ".
    //        "FROM `$index_table` ".
    //        "WHERE `$index_table`.site_id = \"$site_id\"";
    
    $count = exec($sql);
    log("Done pages scan $site_id, $pages pages. $count pages deleted from index.");
  }
  
  //
  // Scan statistic data for existing projects
  //
  private function scan_stats($site_id, $options) {
    extract($options);  
    // 1. Select all records for site_id
    // scan stats
    // Fetch all new projects
    $sql = "SELECT `project_id` ".
           "FROM `$page_table` ".
           "WHERE `mailformed` = 0 and `site_id` = \"$site_id\" and `deadline` > now() and (`state` = \"live\" OR `state` Is Null)";
    
    $stmt = exec($sql);
    $projects = $stmt->fetchAll();
    $stmt->closeCursor();
    $pages = count($projects);
    echo "Scanning $pages pages stats for $site_id.", PHP_EOL;
    log("Total stats rows for $site_id: ".$pages);

    foreach($projects as $project) {
        try {
            Sqissor::process("http://" . $project->project_id, $site_id.".Stats", $options);
        } catch (\Exception $e) {
            echo 'Exception: ', exLine($e), PHP_EOL;
        }
    }
    
    log("Done stats scan $site_id, $pages pages.");
  }
    
  //
  // Scan news from site
  //
  function do_new(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan new SITENAME --maxpage=num';
    }
    $site_id = opt(0);
    $options = $this->getSiteOptions($site_id);
    $maxpage = isset($args['maxpage']) ? $args['maxpage'] : null ;

    try {
      $this->scan_index($site_id, $this->getSiteStartURL($site_id), $options, $maxpage);
    } catch (\Exception $e) {
      echo 'Exception: ', exLine($e), PHP_EOL;
      if (!empty($args['no-ignore'])) { return; }
    }
   
    $this->scan_pages($site_id, $options);
  }
  
  // Scan updates for stats
  function do_stats(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan stats SITENAME';
    }
    $site_id = opt(0);
    $options = $this->getSiteOptions($site_id);

    $this->scan_stats($site_id, $options);
  }

  
  //----------------------------------------------------------------
  //
  // Debug tasks
  //
  //----------------------------------------------------------------
  
  
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
    $options = compact('index_table');

    $this->scan_index($site_id, $url, $options, $maxpage);
  }
  
  // Scan missing pages from index
  function do_pages(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan pages SITENAME INDEX-TABLE PAGE-TABLE';
    }

    $site_id = opt(0);
    $index_table = cfg('dbPrefix').opt(1);
    $page_table = cfg('dbPrefix').opt(2);
    $options = compact('index_table', 'page_table');
    $this->scan_pages($site_id, $options);
  }
  
}