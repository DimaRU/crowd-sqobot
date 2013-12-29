<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterPage extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    static $accept = "text/html";
    
    protected function doSlice($data, array $options) {
        $row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'ref_page' => isset($options['ref_page']) ? $options['ref_page'] : null,
                     'mailformed' => 0
            );
        Row::setTableName($options['table']);
        
        $this->initDom($data);
        try {
            $this->parsePage($data, $row);
        } catch (ESqissor $e) {
            $row['mailformed'] = 1;
            Row::createOrReplaceWith($row);
            throw $e;
        }
        Row::createOrReplaceWith($row);
    }
    
    private function parsePage(&$data, &$row) {
        //$htmlstr = 'window.current_project = "{ .... }";1234';
        if (($pos1 = strpos($data, 'window.current_project')) === false) {
            throw new ESqissor($this, "json data not found");
        }
        $pos1 = strpos($data,'"{', $pos1);
        $pos2 = strpos($data,'}"', $pos1);
        $json = substr($data, $pos1+1, $pos2-$pos1);
        $json = stripslashes(htmlspecialchars_decode($json, ENT_QUOTES));
        $pdata = json_decode($json, true);
        $row['project_json'] = $json;

        //<div class="full-description">
        $row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="full-description"]'));

        /*
        //<div id="risks">
        echo strip_tags($this->queryValue('.//div[@id="risks"]'));
         */

        // <meta meta content="-72.198562622071" property="kickstarter:location:longitude">
        // <meta content="41.333982467652" property="kickstarter:location:latitude">
        $row['latitude'] = $this->queryAttribute('.//meta[@property="kickstarter:location:latitude"]', "content");
        $row['longitude'] = $this->queryAttribute('.//meta[@property="kickstarter:location:longitude"]', "content");

        $row['project_id'] = strstr($pdata['urls']['web']['project'], $this->domain());
        $row['name'] = $pdata['name'];
        $row['blurb'] = $pdata['blurb'];
        $row['avatar'] = strstr(str_replace("https://", "http://", $pdata['photo']['small']), "?", true);
        $row['goal'] = $pdata['goal'];
        $row['country'] = $pdata['country'];
        $row['currency'] = $pdata['currency'];
        $row['currency_symbol'] = $pdata['currency_symbol'];
        $row['currency_trailing_code'] = $pdata['currency_trailing_code'];
        //$st = $pdata['deadline'];
        //$proj['deadline'] = new \DateTime("@$st");
        //$st = $pdata['launched_at'];
        //$proj['launched_at'] = new \DateTime("@$st");
        $row['deadline'] = date(DATE_ATOM,$pdata['deadline']);
        $row['launched_at'] = date(DATE_ATOM,$pdata['launched_at']);

        $row['creator_name'] = $pdata['creator']['name'];
        $row['location'] = $pdata['location']['name'];
        $row['location_url'] = $pdata['location']['urls']['web']['discover'];
        $row['category'] = $pdata['category']['name'];
        $row['short_url'] = $pdata['urls']['web']['project_short'];
    }
}
