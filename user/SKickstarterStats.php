<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterStats extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "text/html";
    
    protected function doSlice($data, array $options) {
        $row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' => strstr($this->url, $this->domain()) );
        
        if (Download::httpReturnCode() == 404) {
            $upd['state'] = "404";
            $where['project_id'] = $row['project_id'];
            Row::setTableName($options['page_table']);
            Row::updateIgnoreWith($upd, $where);
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
            $upd['state'] = $pdata['state'];
            $where['project_id'] = $row['project_id'];
            Row::setTableName($options['page_table']);
            Row::updateIgnoreWith($upd, $where);
        }
        
        //$row['project_json'] = $json;
        $row['pledged'] = $pdata['pledged'];
        $row['backers_count'] = $pdata['backers_count'];
        $row['comments_count'] = $pdata['comments_count'];
        $row['updates_count'] = $pdata['updates_count'];
        Row::setTableName($options['stats_table']);
        Row::createWith($row);
    }
}
