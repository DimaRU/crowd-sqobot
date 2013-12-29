<?php namespace Sqobot;

/*
 * Parse Kickstarter index page:
 * "http://www.kickstarter.com/discover/recently-launched?page=1"
 * 
 */
class SKickstarterIndex extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "application/json";
    
    protected function doSlice($data, array $options) {
        $row = array(
        'site_id' => 'kickstarter',
        'load_time' => date(DATE_ATOM),
        'ref_page' => str_replace("http://", "", $this->url));
        Row::setTableName($options['table']);
        
        $index = json_decode($data, true);

        foreach ($index['projects'] as $project) {
            $row['project_id'] = str_replace("http://", "", $project['urls']['web']['project']);
            Row::createIgnoreWith($row);
        }
        
        // http://www.kickstarter.com/discover/advanced?page=xxx&ref=recently_launched&sort=launch_date
        return $this->incrementPageNum($this->url, "page");
    }
}

?>