<?php namespace Sqobot;

// Scan kickstarter.com new projects
class TaskRescan extends Task {
  public function getDbOptions($site_id) {
    $opt = cfgDbOptions("dbNames");
    return $opt[$site_id];
  }
  
  //
  // Rescan indiegogo
  //
  function do_json(array $args = null) {
    $sql = 'SELECT `project_id` FROM `st_project_page` WHERE `site_id` = "indiegogo" AND isnull(`project_json`) AND isnull(`state`)';
    $stmt = exec($sql);
    $index = $stmt->fetchAll();
    echo "Rescan json for " . count($index) . " records", PHP_EOL;
    $stmt->closeCursor();
    Row::setTableName("st_project_page");
    foreach ($index as $i) {
        $url = "https://".$i->project_id;
        $json = download($url, array('accept' => "application/json"));
        if (Download::httpReturnCode() == 404) {
            Row::updateIgnoreWith(array('state' => "404"), array('project_id' => $i->project_id));
            continue;
        }
        Row::updateIgnoreWith(array('project_json' => $json), array('project_id' => $i->project_id));
    }
  }
}