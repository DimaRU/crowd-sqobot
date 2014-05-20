<?php namespace Sqobot;

/*
 * Parse Kickstarter project page:
 * "http://www.indiegogo.com/project/.......
 * 
 */
class SIndiegogoPage extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    
    protected function doSlice($url) {
        $data = $this->loadURL("https://" . $url, array('accept' => "text/html", 'referer' => $this->getopt('ref_page')));
        if (($newurl = Download::httpMovedURL()) === false) {
            $newurl = "https://" . $url;
        }
        $newurl = str_replace("/x/505059", "", $newurl);

        $row = array( 'site_id' => 'indiegogo',
                      'load_time' => date(DATE_ATOM),
                      'project_id' => strstr($this->url, $this->domain()),  // Cut https://
                      'real_url' => $newurl,
                      'ref_page' => $this->getopt('ref_page'),
                      'mailformed' => 0
        );
        Row::setTableName($this->getopt('page_table'));
        if (Download::httpReturnCode() == 404) {
            $row['state'] = "404";
            Row::createOrReplaceWith($row);
            return;
        }
        $json = $this->loadURL($newurl, array('accept' => "application/json"));
        if (Download::httpReturnCode() == 404) {
            $row['state'] = "404";
            Row::createOrReplaceWith($row);
            return;
        }
        $this->initDom($data);
        try {
            $row += $this->parsePage($json);
        } catch (ESqissor $e) {
            $row['mailformed'] = 1;
            Row::createOrReplaceWith($row);
            throw $e;
        }

        $data1 = $this->loadURL($newurl . "/show_tab/home", array('accept' => "text/html", 'referer' => $newurl));
        if (Download::httpReturnCode() == 404) {
            $row['state'] = "404";
            Row::createOrReplaceWith($row);
            return;
        }
        $this->initDom($data1);
        try {
            $row += $this->parsePageHomeTab();
        } catch (ESqissor $e) {
            $row['mailformed'] = 1;
            Row::createOrReplaceWith($row);
            throw $e;
        }
        
        Row::createOrReplaceWith($row);
    }

    private function parsePageHomeTab() {
        // <a class="i-icon-link js-clip" data-clipboard-text="http://igg.me/at/gosnellmovie/x">
        $row['short_url'] = str_replace("/x", "", $this->queryAttribute('.//a[@class="i-icon-link js-clip"]', "data-clipboard-text"));
        // <a href="/individuals/6877579" class="i-name">Ann and Phelim Media</a>
        $row['creator_name'] = $this->queryValue('.//a[@class="i-name"]');
        $row['full_desc'] = $this->htmlToText($this->queryValue('.//div[@class="i-description"]'));
        return $row;
    }

    private function parsePage($json) {
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

        // <div class="i-img" data-src="https://images.indiegogo.com/projects/731457/pictures/new_baseball/20140327190616-IndieGogo_Image.jpg?1395972381">
        // <meta property="og:image" content="https://images.indiegogo.com/projects/731457/pictures/primary/20140327190616-IndieGogo_Image.jpg?1395972381"/>
        $row['avatar'] = strstr($this->queryAttribute('.//meta[@property="og:image"]', "content"), "?", true);

        // 
        // <div class="i-icon-project-note">
        //  <span class="i-icon i-glyph-icon-22-fixedfunding"></span>
        //  <span>Fixed Funding</span><span class="i-icon i-icon-info-bubble"></span>
        // </div>
        $row['campaign_type'] = trim($this->queryValue('.//div[@class="i-icon-project-note"]'));
        // <a href="/explore?filter_city=Los+Angeles&amp;filter_country=CTRY_US" class="i-byline-location-link">Los Angeles, California, United States</a>
        $row['location_url'] = $this->queryAttribute('.//a[@class="i-byline-location-link"]', "href");
        parse_str($row['location_url'], $output);
        $row['country'] = substr($output['filter_country'], 5);    // skip 'CTRY_'
        return $row;
    }
}
