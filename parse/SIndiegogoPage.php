<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.indiegogo.com/project/.......
 * 
 */
class SIndiegogoPage extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    private $newurl;
    
    protected function startParse() {
        $this->loadURL("https://" . $this->url, array('accept' => "text/html", 'referer' => $this->getopt('ref_page')), array(&$this,'parseData'));
    }
    
    function parseData($httpReturnCode, $data, $httpMovedURL) {
        if (($this->newurl = $httpMovedURL) === false) {
            $this->newurl = "https://" . $this->url;
        }
        $this->newurl = str_replace("/x/505059", "", $this->newurl);

        $this->row = array( 'site_id' => 'indiegogo',
                      'load_time' => date(DATE_ATOM),
                      'project_id' => strstr($this->url, $this->domain()),  // Cut https://
                      'real_url' => $this->newurl,
                      'ref_page' => $this->getopt('ref_page'),
                      'mailformed' => 0
        );
        Row::setTableName($this->getopt('page_table'));
        if ($httpReturnCode == 404) {
            $this->row['state'] = "404";
            Row::createOrReplaceWith($this->row);
            return;
        }

        $this->initDom($data);
        try {
            $this->parsePage();
        } catch (ESqissor $e) {
            $this->row['mailformed'] = 1;
            Row::createOrReplaceWith($this->row);
            throw $e;
        }
        $this->loadURL($this->newurl . "/show_tab/home", array('accept' => "text/html", 'referer' => $this->newurl), array(&$this,'parsePageHomeTab'));
    }
    
    function parsePageHomeTab($httpReturnCode, $data, $httpMovedURL) {
        if ($httpReturnCode == 404) {
            $this->row['state'] = "404";
            Row::createOrReplaceWith($this->row);
            return;
        }
        $this->initDom($data);
        try {
            $this->parsePageHomeTab1();
        } catch (ESqissor $e) {
            $this->row['mailformed'] = 1;
            Row::createOrReplaceWith($this->row);
            throw $e;
        }
        
        $this->loadURL($this->newurl, array('accept' => "application/json"), array(&$this,'parseJson'));
    }

    function parseJson($httpReturnCode, $data, $httpMovedURL) {
        if ($httpReturnCode == 404) {
            $this->row['state'] = "404";
            Row::createOrReplaceWith($this->row);
            return;
        }
        
        try {
            $this->parseJson1($data);
        } catch (ESqissor $e) {
            $this->row['mailformed'] = 1;
            Row::createOrReplaceWith($this->row);
            throw $e;
        }

        Row::createOrReplaceWith($this->row);
    }
    
    private function parsePageHomeTab1() {
        // <a class="i-icon-link js-clip" data-clipboard-text="http://igg.me/at/gosnellmovie/x">
        $this->row['short_url'] = str_replace("/x", "", $this->queryAttribute('.//a[@class="i-icon-link js-clip"]', "data-clipboard-text"));
        // <a href="/individuals/6877579" class="i-name">Ann and Phelim Media</a>
        $this->row['creator_name'] = $this->queryValue('.//a[@class="i-name"]');
        $this->row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="i-description"]'));
    }

    private function parsePage() {
        // <div class="i-img" data-src="https://images.indiegogo.com/projects/731457/pictures/new_baseball/20140327190616-IndieGogo_Image.jpg?1395972381">
        // <meta property="og:image" content="https://images.indiegogo.com/projects/731457/pictures/primary/20140327190616-IndieGogo_Image.jpg?1395972381"/>
        $this->row['avatar'] = strstr($this->queryAttribute('.//meta[@property="og:image"]', "content"), "?", true);
        // <div class="i-icon-project-note">
        //  <span class="i-icon i-glyph-icon-22-fixedfunding"></span>
        //  <span>Fixed Funding</span><span class="i-icon i-icon-info-bubble"></span>
        // </div>
        $this->row['campaign_type'] = trim($this->queryValue('.//div[@class="i-icon-project-note"]'));
        // <a href="/explore?filter_city=Los+Angeles&amp;filter_country=CTRY_US" class="i-byline-location-link">Los Angeles, California, United States</a>
        $this->row['location_url'] = $this->queryAttribute('.//a[@class="i-byline-location-link"]', "href");
        parse_str($this->row['location_url'], $output);
        $this->row['country'] = substr($output['filter_country'], 5);    // skip 'CTRY_'
    }

    private function parseJson1($json) {
        $pdata = json_decode($json, true);
        $this->row['name'] = $pdata['title'];
        $this->row['blurb'] = $pdata['tagline'];
        $this->row['currency_symbol'] = $pdata['currency_symbol'];
        $this->row['goal'] = $pdata['goal'];
        $this->row['currency'] = $pdata['currency'];
        $this->row['launched_at'] = date(DATE_ATOM, strtotime($pdata['funding_started_at']));
        $this->row['deadline'] = date(DATE_ATOM, strtotime($pdata['funding_ends_at']));
        $this->row['location'] = $pdata['city'];
        $this->row['category'] = $pdata['category'];
        $this->row['project_json'] = $json;
    }
}
