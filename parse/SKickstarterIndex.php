<?php namespace Sqobot;

/*
 * Parse Kickstarter index page:
 * "http://www.kickstarter.com/discover/recently-launched?page=1"
 * 
 */
class SKickstarterIndex extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    
    protected function startParse() {
        $this->loadURL($this->url, array('accept' => "application/json"), array(&$this,'parseData'));
        // http://www.kickstarter.com/discover/advanced?page=xxx&ref=recently_launched&sort=launch_date
        return $this->incrementPageNum($this->url, "page");
    }
    
    function parseData($httpReturnCode, $data, $httpMovedURL) {
        if ($httpReturnCode == 404) {
            return;
        }
        
        $this->row = array(
        'site_id' => 'kickstarter',
        'load_time' => date(DATE_ATOM),
        'ref_page' => strstr($this->url, $this->domain()) );
        Row::setTableNameKey($this->getopt('index_table'));

        $index = json_decode($data, true);
        foreach ($index['projects'] as $project) {
            $this->row['project_id'] = strstr($project['urls']['web']['project'], $this->domain());
            Row::createIgnoreWith($this->row);
        }
    }
}
