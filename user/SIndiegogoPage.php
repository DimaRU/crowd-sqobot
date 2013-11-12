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
                      'ref_page' => isset($extra['ref_page']) ? $extra['ref_page'] : null
            );

        // Clean up html thru tydy lib
        $config = array('indent' => false,
                   'output-html' => true,
                   'wrap'        => 0);
        $tidy = new \tidy;
        $clean_data = $tidy->repairString($data, $config, 'utf8');
        unset($tidy);

        $html = new \DOMDocument();
        $html->validateOnParse = true;
        $cur = libxml_use_internal_errors(true);
        $html->loadHTML($clean_data);
        libxml_use_internal_errors($cur);
        $finder = new \DomXPath($html);
/*

    <meta property="og:title" content="Flauntin - get discount when promoting your favorite stores!"/>
    <meta property="og:description" content="Our goal is to vitalize small, local and independent shops around the world!"/>
    <meta property="og:url" content="http://www.indiegogo.com/projects/566291/fblk"/>
    <p class="money-raised goal">Raised of <span class="currency"><span>$5,000</span></span> Goal</p>
    <p class="amount bold fl title">Flexible Funding</p>
    <p class="funding-info">This campaign will receive all funds raised ..... Funding duration: November 04, 2013 - December 27, 2013 (11:59pm PT).</p>
    <div class="user-content">
 */

        $nodes = $finder->query('.//meta[@property="og:title"]');
        $row['name'] = $nodes->item(0)->getAttribute("content");

        $nodes = $finder->query('.//meta[@property="og:description"]');
        $row['blurb'] = $nodes->item(0)->getAttribute("content");

        $nodes = $finder->query('.//input[@name="sharing_url"]');
        $row['short_url'] = $nodes->item(0)->getAttribute("value");

        // <span class="category"><a href="/projects?filter_category=Community">Community</a>
        $nodes = $finder->query('.//span[@class="category"]/a');
        $row['category'] = $nodes->item(0)->nodeValue;

        $nodes = $finder->query('.//p[@class="money-raised goal"]/span[@class="currency"]/span');
        $s = strpbrk($nodes->item(0)->nodeValue, "0123456789");
        $row['currency_symbol'] = str_replace($s, "", $nodes->item(0)->nodeValue);
        $row['goal'] = str_replace(",", "", $s);

        //<span class="currency currency-xlarge"><span>£26,316</span><em>GBP</em></span></span>
        $nodes = $finder->query('.//span[@class="currency currency-xlarge"]/em');
        $row['currency'] = $nodes->item(0)->nodeValue;

        $nodes = $finder->query('.//p[@class="amount bold fl title"]');
        $row['campaign_type'] = $nodes->item(0)->nodeValue;

        // <div class="fl information"><a href="/individuals/4630424" class="name bold">Sanderson Jones</a>
        $nodes = $finder->query('.//div[@class="fl information"]/a');
        $row['creator_name'] = $nodes->item(0)->nodeValue;

        if ($row['name']) {
            // Location
            // <span class="location"><a href="/projects?filter_city=London&amp;cGB&amp;filter_text=">London, United Kingdom</a>
            $nodes = $finder->query('.//span[@class="location"]/a');
            $row['location_url'] = $nodes->item(0)->getAttribute("href");
            $row['location'] = $nodes->item(0)->nodeValue;
            $parts = explode("&", $nodes->item(0)->getAttribute("href"));
            $row['country'] = substr($parts[1], 20);    // skip 'filter_country=CTRY_'
        }
        else {
            $row['location_url'] = "";
            $row['location'] = "";
            $row['country'] = "";
        }

        // ...duration: November 04, 2013 - December 27, 2013 (11:59pm PT).
        $nodes = $finder->query('.//p[@class="funding-info"]');
        $s = $nodes->item(0)->nodeValue;
        $s = substr($s, strpos($s, 'duration: ')+10);
        $dates = explode("-", str_replace(" (11:59pm PT).", "", $s));
        $row['launched_at'] = date(DATE_ATOM, strtotime($dates[0]));
        $row['deadline'] = date(DATE_ATOM, strtotime($dates[1]));

        $nodes = $finder->query('.//div[@class="user-content"]');
        $row['full_desc'] = $this->htmlToText($nodes->item(0)->nodeValue);
        
        IndiegogoPageRow::createWith($row);
        
        unset($finder);
        unset($html);
    }
}
