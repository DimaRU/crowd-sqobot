<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SKickstarterPage extends Sqissor {
    static $domain_name = 'www.kickstarter.com';
    
    protected function doSlice($data, array $extra) {
        $row = array('site_id' => 'kickstarter', 
                     'load_time' => date(DATE_ATOM),
                     'ref_page' => isset($extra['ref_page']) ? $extra['ref_page'] : null
            );

        // Clean up html thru tydy lib
        $config = array('indent' => false,
                   'output-html' => true,
                   'wrap'        => 0);
        $tidy = new \tidy;
        $clean_data = $tidy->repairString($data, $config, 'utf8');

        $html = new \DOMDocument();
        $html->validateOnParse = true;
        $cur = libxml_use_internal_errors(true);
        $html->loadHTML($clean_data);
        libxml_use_internal_errors($cur);

        //$htmlstr = 'window.current_project = "{ .... }";1234';
        $pos1 = strpos($data, 'window.current_project');
        $pos1 = strpos($data,'"{', $pos1);
        $pos2 = strpos($data,'}"', $pos1);
        $json = substr($data, $pos1+1, $pos2-$pos1);
        $json = stripslashes(htmlspecialchars_decode($json, ENT_QUOTES));
        $pdata = json_decode($json, true);
        $row['project_json'] = $json;

                
        $finder = new \DomXPath($html);

        //<div class="full-description">
        $nodes = $finder->query('.//div[@class="full-description"]');
        $row['full_desc'] = $this->htmlToText($nodes->item(0)->nodeValue);

        /*
        //<div id="risks">
        $nodes = $finder->query('.//div[@id="risks"]');
        echo strip_tags($nodes->item(0)->nodeValue);
         */

        // <meta meta content="-72.198562622071" property="kickstarter:location:longitude">
        // <meta content="41.333982467652" property="kickstarter:location:latitude">
        $nodes = $finder->query('.//meta[@property="kickstarter:location:latitude"]');
        $row['latitude'] = $nodes->item(0)->getAttribute("content");
        $nodes = $finder->query('.//meta[@property="kickstarter:location:longitude"]');
        $row['longitude'] = $nodes->item(0)->getAttribute("content");

        
        $row['project_id'] = strstr($pdata['urls']['web']['project'], $this->domain());
        $row['name'] = $pdata['name'];
        $row['blurb'] = $pdata['blurb'];
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
        
        KickstarterPageRow::createWith($row);
    }
}
