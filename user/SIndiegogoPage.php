<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SIndiegogoPage extends Sqissor {
    static $domain_name = 'www.indiegogo.com';

    protected function doSlice($data, array $extra) {
        $row = array( 'site_id' => 'indiegogo',
                      'load_time' => date(DATE_ATOM),
                      'project_id' => strstr($this->url, $this->domain()),
                      'ref_page' => isset($extra['ref_page']) ? $extra['ref_page'] : null,
                      'mailformed' => 0
        );
        $this->initDom($data);
        try {
            $this->parsePage($row);
        } catch (ESqissor $e) {
            $row['mailformed'] = 1;
            ProjectPageRow::createOrReplaceWith($row);
            throw $e;
        }
        ProjectPageRow::createOrReplaceWith($row);
    }

    private function parsePage(&$row) {
    /*
        <meta property="og:title" content="Flauntin - get discount when promoting your favorite stores!"/>
        <meta property="og:description" content="Our goal is to vitalize small, local and independent shops around the world!"/>
        <meta property="og:url" content="http://www.indiegogo.com/projects/566291/fblk"/>
        <p class="money-raised goal">Raised of <span class="currency"><span>$5,000</span></span> Goal</p>
        <p class="amount bold fl title">Flexible Funding</p>
        <p class="funding-info">This campaign will receive all funds raised ..... Funding duration: November 04, 2013 - December 27, 2013 (11:59pm PT).</p>
        <div class="user-content">
     */

        $row['name'] = $this->queryAttribute('.//meta[@property="og:title"]', "content");
        if (!$row['name'] or strpos($row['name'], 'Untitled Draft Project') !== false) {
            warn($this->url . ": bad project name: {$row['name']}");
            $row['mailformed'] = 1;
            return;
        }

        $row['blurb'] = $this->queryAttribute('.//meta[@property="og:description"]', "content");
        $row['short_url'] = $this->queryAttribute('.//input[@name="sharing_url"]', "value");

        // <span class="category"><a href="/projects?filter_category=Community">Community</a>
        $row['category'] = $this->queryValue('.//span[@class="category"]/a');

        $g = $this->queryValue('.//p[@class="money-raised goal"]/span[@class="currency"]/span');
        $s = strpbrk($g, "0123456789");
        $row['currency_symbol'] = str_replace($s, "", $g);
        $row['goal'] = str_replace(",", "", $s);

        //<span class="currency currency-xlarge"><span>£26,316</span><em>GBP</em></span></span>
        $row['currency'] = $this->queryValue('.//span[@class="currency currency-xlarge"]/em');
        $row['campaign_type'] = $this->queryValue('.//p[@class="amount bold fl title"]');
        // <div class="fl information"><a href="/individuals/4630424" class="name bold">Sanderson Jones</a>
        $row['creator_name'] = $this->queryValue('.//div[@class="fl information"]/a');
        // Location
        // <span class="location"><a href="/projects?filter_city=London&amp;cGB&amp;filter_text=">London, United Kingdom</a>
        $row['location_url'] = $this->queryAttribute('.//span[@class="location"]/a', "href");
        $row['location'] = $this->queryValue('.//span[@class="location"]/a');
        $parts = explode("&", $row['location_url']);
        $row['country'] = substr($parts[1], 20);    // skip 'filter_country=CTRY_'

        // ...duration: November 04, 2013 - December 27, 2013 (11:59pm PT).
        $s = $this->queryValue('.//p[@class="funding-info"]');
        $s = substr($s, strpos($s, 'duration: ')+10);
        $dates = explode("-", str_replace(" (11:59pm PT).", "", $s));
        $row['launched_at'] = date(DATE_ATOM, strtotime($dates[0]));
        $row['deadline'] = date(DATE_ATOM, strtotime($dates[1]));
        $row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="user-content"]'));
    }
}
