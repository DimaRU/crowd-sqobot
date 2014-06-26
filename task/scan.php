<?php namespace Sqobot;

// Scan kickstarter.com new projects
class TaskScan extends Task {
  public function getDbOptions($site_id) {
    $opt = cfgDbOptions("dbNames");
    return $opt[$site_id];
  }
  
  public function getSiteStartURL($site_id) {
    $opt = cfgOptions("scan");
    return $opt[$site_id]['start_page'];
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
    
    finishDownload();
    
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
            Sqissor::process($project->project_id, $site_id.".Page", $options);
        } catch (\Exception $e) {
            echo 'Exception: ', exLine($e), PHP_EOL;
        }
    }
    finishDownload();
    
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
    $sql = "SELECT `project_id`, `real_url` ".
           "FROM `$page_table` ".
           "WHERE `mailformed` = 0 and `site_id` = \"$site_id\" and `deadline` > now() and (`state` = \"live\" OR `state` Is Null)";
    
    $stmt = exec($sql);
    $projects = $stmt->fetchAll();
    $stmt->closeCursor();
    $pages = count($projects);
    echo "Scanning $pages pages stats for $site_id.", PHP_EOL;
    log("Total stats rows for $site_id: ".$pages);
    $newrows = 0;
    
    foreach($projects as $project) {
        try {
            $options['real_url'] = $project->real_url;
            Sqissor::process($project->project_id, $site_id.".Stats", $options) and $newrows++;
        } catch (\Exception $e) {
            echo 'Exception: ', exLine($e), PHP_EOL;
        }
    }
    finishDownload();

    log("Done scan stats $site_id, rows processed/new: $pages/$newrows.");
  }
    
  //
  // Scan news from site
  //
  function do_new(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'scan new SITENAME --maxpage=num';
    }
    $site_id = opt(0);
    $options = $this->getDbOptions($site_id);
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
    $options = $this->getDbOptions($site_id);

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
    finishDownload();
    if ($url) {
        echo "Returned $url", PHP_EOL;
    }
  }
    
  // Scan index
  function do_index(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan index SITENAME URL [OPTION=[VALUE] [...]] --maxpage=num';
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
    $maxpage = isset($args['maxpage']) ? $args['maxpage'] : null ;

    $this->scan_index($site_id, $url, $options, $maxpage);
  }
  
  // Scan missing pages from index
  function do_pages(array $args = null) {
    if ($args === null or !opt(2)) {
      return print 'scan pages SITENAME [OPTION=[VALUE] [...]]';
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

    $this->scan_pages($site_id, $options);
  }
  
}
