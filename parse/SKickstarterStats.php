<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterStats extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    
    protected function compareRows(array $row1, array $row2) {
        return ( $row1['pledged'] == $row2[0]->pledged &&
             $row1['backers_count'] == $row2[0]->backers_count &&
             $row1['comments_count'] == $row2[0]->comments_count &&
             $row1['updates_count'] == $row2[0]->updates_count);
    }
    
    protected function startParse() {
        download("https://" . $this->url, array('accept' => "text/html"), array(&$this,'parseData'));
    }
    
    function parseData(Download $dw) {
        $project_id = strstr($this->url, $this->domain());
        $this->row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' =>  $project_id);
        
        if ($dw->httpReturnCode() == 404) {
            Row::setTableName($this->getopt('page_table'));
            Row::updateIgnoreWith(array('state' => "404"), array('project_id' => $project_id));
            return;
        }

        $data = $dw->getContent();
        //$htmlstr = 'window.current_project = "{ .... }";1234';
        if (($pos1 = strpos($data, 'window.current_project')) === false) {
            throw new ESqissor($this, "json data not found");
        }
        $pos1 = strpos($data,'"{', $pos1);
        $pos2 = strpos($data,'}"', $pos1);
        $json = substr($data, $pos1+1, $pos2-$pos1);
        $json = stripslashes(htmlspecialchars_decode($json, ENT_QUOTES));
        $pdata = json_decode($json, true);
        
        if ($pdata['state'] != 'live') {
            Row::setTableName($this->getopt('page_table'));
            Row::updateIgnoreWith(array('state' => $pdata['state']), array('project_id' => $project_id));
        }
        
        $this->row['pledged'] = $pdata['pledged'];
        $this->row['backers_count'] = $pdata['backers_count'];
        $this->row['comments_count'] = $pdata['comments_count'];
        $this->row['updates_count'] = $pdata['updates_count'];

        if (!$this->row['pledged'] && !$this->row['backers_count'] && !$this->row['comments_count'] && !$this->row['updates_count'] = 0)
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

        if (count($lastrow) != 0 && $this->compareRows($this->row, $lastrow)) {
            // Update date ?
            /* Row::setTableName($this->getopt('stats_table'));
            Row::updateIgnoreWith(array('load_time' => $this->row['load_time']), 
                    array('project_id' => $project_id, 'load_time' => $lastrow[0]->load_time));
             */
        } else {
            // Add new record
            Row::setTableName($this->getopt('stats_table'));
            Row::createWith($this->row);
            return true;
        }
    }
}
