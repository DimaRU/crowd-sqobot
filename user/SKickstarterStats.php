<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterStats extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "application/json";
    
    protected function doSlice($data, array $options) {
        $row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' => strstr($this->url, $this->domain()) );
        
        Row::setTableName($options['page_table']);
        if (Download::httpReturnCode() == 404) {
            $row['mailformed'] = 1;
            Row::updateIgnoreWith($row);
            return;
        }
        
        $pdata = json_decode($data, true);
        if ($pdata['state'] != 'live') {
            $pagerow = $row;
            $pagerow['state'] = $pdata['state'];
            Row::updateIgnoreWith($row);
        }
        
        $row['project_json'] = $data;
        $row['pledged'] = $pdata['pledged'];
        $row['backers_count'] = $pdata['backers_count'];
        $row['comments_count'] = $pdata['comments_count'];
        $row['updates_count'] = $pdata['updates_count'];
        Row::setTableName($options['stats_table']);
        Row::createWith($row);
    }
}
