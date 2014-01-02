<?php namespace Sqobot;

/*
 * Parse Indiegogo json project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SIndiegogoStats extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    static $accept = "application/json";
    
    protected function doSlice($data, array $options) {
        $row = array('site_id' => 'indiegogo', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' => strstr($this->url, $this->domain()) );
        
        Row::setTableName($options['page_table']);
        if (Download::httpReturnCode() == 404) {
            $row['mailformed'] = 1;
            Row::updateIgnoreWith($row);
            return;
        }
        $pdata = json_decode($data, true);

        $row['project_json'] = $data;
        $row['pledged'] = $pdata['balance'];
        $row['backers_count'] = $pdata['funders'];
        $row['comments_count'] = $pdata['comments'];
        //$row['updates_count'] = $pdata['updates_count'];
        Row::setTableName($options['stats_table']);
        Row::createWith($row);
    }
}
