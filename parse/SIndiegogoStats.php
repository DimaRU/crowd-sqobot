<?php namespace Sqobot;

/*
 * Parse Indiegogo json project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SIndiegogoStats extends Sqissor {
    static $domain_name = 'www.indiegogo.com';

    protected function compareRows(array $row1, array $row2) {
        return ( $row1['pledged'] == $row2[0]->pledged &&
             $row1['backers_count'] == $row2[0]->backers_count &&
             $row1['comments_count'] == $row2[0]->comments_count &&
             $row1['updates_count'] == $row2[0]->updates_count);
    }
    
    protected function doSlice($url) {
        $data = $this->loadURL($this->getopt('real_url'), array('accept' => "application/json"));

        $project_id = $url;
        $row = array('site_id' => 'indiegogo', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' =>  $project_id);

        // Trace project remove
        if (Download::httpReturnCode() == 404) {
            Row::setTableName($this->getopt('page_table'));
            Row::updateIgnoreWith(array('state' => "404"), array('project_id' => $project_id));
            return;
        }

        // Project rename
        if (($newurl = Download::httpMovedURL()) !== false) {
            // Mark old project page
            warn("Renamed ".$this->url);
            Row::setTableName($this->getopt('page_table'));
            Row::updateIgnoreWith(array('real_url' => $newurl), array('project_id' => $project_id));
            // TODO: Rescan project
            //$idx = $row;
            //$idx['ref_page'] = $newurl;                  // old
            //Row::setTableName($this->getopt('index_table'));
            //Row::createOrReplaceWith($idx);
        }

        $pdata = json_decode($data, true);
        //$row['project_json'] = $data;
        $row['pledged'] = $pdata['balance'];
        $row['backers_count'] = $pdata['funders'];
        $row['comments_count'] = $pdata['comments'];
        $row['updates_count'] = 0;

        if (!$row['pledged'] && !$row['backers_count'] && !$row['comments_count'] && !$row['updates_count'] = 0)
        {  return; }
        
        // Compare with old
        $sql = "SELECT `load_time`, `pledged`, `backers_count`, `comments_count`, `updates_count`\n"
            . "FROM `st_project_stats`\n"
            . "WHERE `project_id`=\"$project_id\"\n"
            . "ORDER BY `load_time` DESC\n"
            . "LIMIT 1";
        $stmt = exec($sql);
        $lastrow = $stmt->fetchAll();
        $stmt->closeCursor();

        if (count($lastrow) == 0 || !$this->compareRows($row, $lastrow)) {
            // Add new record
            Row::setTableName($this->getopt('stats_table'));
            Row::createWith($row);
            return true;
        }
    }
}
