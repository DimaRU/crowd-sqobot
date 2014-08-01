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

    protected function startParse() {
        $this->loadURL("https://" . $this->getopt('real_url'), array('accept' => "application/json"), array(&$this,'parseData'));
    }
    
    function parseData($httpReturnCode, $data, $httpMovedURL) {
        $this->row = array('site_id' => 'indiegogo', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' =>  $this->url);

        // Trace project remove
        if ($httpReturnCode == 404) {
            Row::setTableNameKey($this->getopt('page_table'));
            Row::updateIgnoreWith(array('state' => "404"), array('project_id' => $this->url));
            return;
        }

        // Project rename
        if ($httpMovedURL !== false) {
            // Mark old project page
            warn("Renamed ".$this->url);
            Row::setTableNameKey($this->getopt('page_table'));
            Row::updateIgnoreWith(array('real_url' => strstr($httpMovedURL, $this->domain()) ), 
                    array('project_id' => $this->url));
            // TODO: Rescan project
            //$idx = $this->row;
            //$idx['ref_page'] = $newurl;                  // old
            //Row::setTableName($this->getopt('index_table'));
            //Row::createOrReplaceWith($idx);
        }

        $pdata = json_decode($data, true);
        $this->row['pledged'] = $pdata['balance'];
        $this->row['backers_count'] = $pdata['funders'];
        $this->row['comments_count'] = $pdata['comments'];
        $this->row['updates_count'] = 0;

        if (!$this->row['pledged'] && !$this->row['backers_count'] && !$this->row['comments_count'] && !$this->row['updates_count'] = 0)
        {  return; }
        
        // Compare with old
        $sql = "SELECT `load_time`, `pledged`, `backers_count`, `comments_count`, `updates_count`\n"
            . "FROM `st_project_stats`\n"
            . "WHERE `project_id`=\"$this->url\"\n"
            . "ORDER BY `load_time` DESC\n"
            . "LIMIT 1";
        $stmt = exec($sql);
        $lastrow = $stmt->fetchAll();
        $stmt->closeCursor();

        if (count($lastrow) == 0 || !$this->compareRows($this->row, $lastrow)) {
            // Add new record
            Row::setTableNameKey($this->getopt('stats_table'));
            Row::createWith($this->row);
        }
    }
}
