<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.indiegogo.com/project/.......
 * 
 */
class SIndiegogoPage extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    static $accept = "text/html";
    
    protected function doSlice($data) {
        $row = array( 'site_id' => 'indiegogo',
                      'load_time' => date(DATE_ATOM),
                      'project_id' => strstr($this->url, $this->domain()),
                      'ref_page' => $this->getopt('ref_page'),
                      'mailformed' => 0
        );
        Row::setTableName($this->getopt('page_table'));
        if (Download::httpReturnCode() == 404) {
            $row['state'] = "404";
            Row::createOrReplaceWith($row);
            return;
        }

        $this->initDom($data);
        $json = download($this->url, array('referer' => $row['ref_page'], 'accept' => "application/json"));
        try {
            $this->parsePage($row, $json);
        } catch (ESqissor $e) {
            $row['mailformed'] = 1;
            Row::createOrReplaceWith($row);
            throw $e;
        }
        Row::createOrReplaceWith($row);
    }

    private function parsePage(&$row, &$json) {
    /*
        <meta property="og:title" content="Flauntin - get discount when promoting your favorite stores!"/>
        <meta property="og:description" content="Our goal is to vitalize small, local and independent shops around the world!"/>
        <meta property="og:url" content="http://www.indiegogo.com/projects/566291/fblk"/>
        <p class="money-raised goal">Raised of <span class="currency"><span>$5,000</span></span> Goal</p>
        <p class="amount bold fl title">Flexible Funding</p>
        <p class="funding-info">This campaign will receive all funds raised ..... Funding duration: November 04, 2013 - December 27, 2013 (11:59pm PT).</p>
        <div class="user-content">
     */

        $pdata = json_decode($json, true);
        $row['name'] = $pdata['title'];
        $row['blurb'] = $pdata['tagline'];
        $row['currency_symbol'] = $pdata['currency_symbol'];
        $row['goal'] = $pdata['goal'];
        $row['currency'] = $pdata['currency'];
        $row['launched_at'] = date(DATE_ATOM, strtotime($pdata['funding_started_at']));
        $row['deadline'] = date(DATE_ATOM, strtotime($pdata['funding_ends_at']));
        $row['location'] = $pdata['city'];
        $row['category'] = $pdata['category'];
        $row['project_json'] = $json;

        /*
        $row['name'] = $this->queryAttribute('.//meta[@property="og:title"]', "content");
        if (!$row['name'] or strpos($row['name'], 'Untitled Draft Project') !== false) {
            warn($this->url . ": bad project name: {$row['name']}");
            $row['mailformed'] = 1;
            return;
        }
        $row['blurb'] = $this->queryAttribute('.//meta[@property="og:description"]', "content");
        // <span class="category"><a href="/projects?filter_category=Community">Community</a>
        $row['category'] = $this->queryValue('.//span[@class="category"]/a');
        $g = $this->queryValue('.//p[@class="money-raised goal"]/span[@class="currency"]/span');
        $s = strpbrk($g, "0123456789");
        $row['currency_symbol'] = str_replace($s, "", $g);
        $row['goal'] = str_replace(",", "", $s);

        //<span class="currency currency-xlarge"><span>£26,316</span><em>GBP</em></span></span>
        $row['currency'] = $this->queryValue('.//span[@class="currency currency-xlarge"]/em');

        // ...duration: November 04, 2013 - December 27, 2013 (11:59pm PT).
        $s = $this->queryValue('.//p[@class="funding-info"]');
        $s = substr($s, strpos($s, 'duration: ')+10);
        $dates = explode("-", str_replace(" (11:59pm PT).", "", $s));
        $row['launched_at'] = date(DATE_ATOM, strtotime($dates[0]));
        $row['deadline'] = date(DATE_ATOM, strtotime($dates[1]));
        $row['location'] = $this->queryValue('.//span[@class="location"]/a');
         */
        $row['campaign_type'] = $this->queryValue('.//p[@class="amount bold fl title"]');
        $row['short_url'] = $this->queryAttribute('.//input[@name="sharing_url"]', "value");
        $row['avatar'] = str_replace("thumbnail", "baseball", strstr($this->queryAttribute('.//img[@class="fl avatar"]', "src"), "?", true));
        // <div class="fl information member"><a href="/individuals/4630424" class="name bold">Sanderson Jones</a>
        $row['creator_name'] = $this->queryValue('.//div[@class="fl information member"]/a');
        // Location
        // <span class="location"><a href="/projects?filter_city=London&amp;cGB&amp;filter_text=">London, United Kingdom</a>
        $row['location_url'] = $this->queryAttribute('.//span[@class="location"]/a', "href");
        $parts = explode("&", $row['location_url']);
        $row['country'] = substr($parts[1], 20);    // skip 'filter_country=CTRY_'
        $row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="user-content"]'));
    }
}
