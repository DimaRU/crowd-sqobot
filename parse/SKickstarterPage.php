<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterPage extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "text/html";
    
    protected function startParse() {
        $this->loadURL("https://" . $this->url, array('accept' => "text/html", 'referer' => $this->getopt('ref_page')), array(&$this,'parseData'));
    }
    
    function parseData($httpReturnCode, $data, $httpMovedURL) {
        $this->row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' => strstr($this->url, $this->domain()),
                     'ref_page' => $this->getopt('ref_page'),
                     'mailformed' => 0
            );
        Row::setTableName($this->getopt('page_table'));

        if ($httpReturnCode == 404) {
            $this->row['state'] = "404";
            Row::createOrReplaceWith($this->row);
            return;
        }

        try {
            $this->parsePage($data);
        } catch (ESqissor $e) {
            $this->row['mailformed'] = 1;
            Row::createOrReplaceWith($this->row);
            throw $e;
        }
        Row::createOrReplaceWith($this->row);
    }

    private function parsePage($data) {
        $this->initDom($data);
        //$htmlstr = 'window.current_project = "{ .... }";1234';
        if (($pos1 = strpos($data, 'window.current_project')) === false) {
            throw new ESqissor($this, "json data not found");
        }
        $pos1 = strpos($data,'"{', $pos1);
        $pos2 = strpos($data,'}"', $pos1);
        $json = substr($data, $pos1+1, $pos2-$pos1);
        $json = stripslashes(htmlspecialchars_decode($json, ENT_QUOTES));
        $pdata = json_decode($json, true);
        $this->row['project_json'] = $json;

        //<div class="full-description">
        $this->row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="full-description"]'));

        /*
        //<div id="risks">
        echo strip_tags($this->queryValue('.//div[@id="risks"]'));
         */

        // <meta content="-72.198562622071" property="kickstarter:location:longitude">
        // <meta content="41.333982467652" property="kickstarter:location:latitude">
        //$this->row['latitude'] = $this->queryAttribute('.//meta[@property="kickstarter:location:latitude"]', "content");
        //$this->row['longitude'] = $this->queryAttribute('.//meta[@property="kickstarter:location:longitude"]', "content");

        $this->row['project_id'] = strstr($pdata['urls']['web']['project'], $this->domain());
        $this->row['name'] = $pdata['name'];
        $this->row['blurb'] = $pdata['blurb'];
        $this->row['avatar'] = strstr($pdata['photo']['little'], "?", true);
        $this->row['goal'] = $pdata['goal'];
        $this->row['country'] = $pdata['country'];
        $this->row['currency'] = $pdata['currency'];
        $this->row['currency_symbol'] = $pdata['currency_symbol'];
        $this->row['currency_trailing_code'] = $pdata['currency_trailing_code'];
        //$st = $pdata['deadline'];
        //$proj['deadline'] = new \DateTime("@$st");
        //$st = $pdata['launched_at'];
        //$proj['launched_at'] = new \DateTime("@$st");
        $this->row['deadline'] = date(DATE_ATOM,$pdata['deadline']);
        $this->row['launched_at'] = date(DATE_ATOM,$pdata['launched_at']);

        $this->row['creator_name'] = $pdata['creator']['name'];
        $this->row['location'] = $pdata['location']['name'];
        $this->row['location_url'] = $pdata['location']['urls']['web']['discover'];
        $this->row['category'] = $pdata['category']['name'];
        $this->row['short_url'] = $pdata['urls']['web']['project_short'];
        if ($pdata['state'] != 'live') { $this->row['state'] = $pdata['state']; }
    }
}
