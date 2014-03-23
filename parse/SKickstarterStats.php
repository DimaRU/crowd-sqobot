<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterStats extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "text/html";
    
    protected function compareRows(array $row1, array $row2) {
        return ( $row1['pledged'] == $row2[0]->pledged &&
             $row1['backers_count'] == $row2[0]->backers_count &&
             $row1['comments_count'] == $row2[0]->comments_count &&
             $row1['updates_count'] == $row2[0]->updates_count);
    }
    
    protected function doSlice($data) {
        $project_id = strstr($this->url, $this->domain());
        $row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' =>  $project_id);
        
        if (Download::httpReturnCode() == 404) {
            Row::setTableName($this->getopt('page_table'));
            Row::updateIgnoreWith(array('state' => "404"), array('project_id' => $project_id));
            return;
        }

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
        
        //$row['project_json'] = $json;
        $row['pledged'] = $pdata['pledged'];
        $row['backers_count'] = $pdata['backers_count'];
        $row['comments_count'] = $pdata['comments_count'];
        $row['updates_count'] = $pdata['updates_count'];

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

        if (count($lastrow) != 0 && $this->compareRows($row, $lastrow)) {
            // Update date ?
            /* Row::setTableName($this->getopt('stats_table'));
            Row::updateIgnoreWith(array('load_time' => $row['load_time']), 
                    array('project_id' => $project_id, 'load_time' => $lastrow[0]->load_time));
             */
        } else {
            // Add new record
            Row::setTableName($this->getopt('stats_table'));
            Row::createWith($row);
            return true;
        }
    }
}
