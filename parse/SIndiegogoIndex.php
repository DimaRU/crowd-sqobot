<?php namespace Sqobot;

/*
 * Parse Indiegogo index page:
 * "http://www.indiegogo.com/projects?filter_quick=new&pg_num=1"
 * 
 */
class SIndiegogoIndex extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    static $accept = "text/html";
    
    protected function doSlice($data) {
        if (Download::httpReturnCode() == 404)
            return;
        
        $row = array(
        'site_id' => 'indiegogo',
        'load_time' => date(DATE_ATOM),
        'ref_page' => $this->url);
        Row::setTableName($this->getopt('index_table'));
        
        $this->initDom($data);
        
        // <div class="project-details"> <a href="/projects/start-anew-world/pinw" class="name bold">Start Anew World</a>
        $projects_index = $this->querySafe('.//div[@class="project-details"]/a');

        foreach ($projects_index as $project) {
            $s = $project->getAttribute('href');
            $row['project_id'] = $this->domain() . substr($s, 0, strrpos($s,"/"));
            Row::createOrReplaceWith($row);
        }
        // <div class="browse_pagination" locale="en">
        // <a href="/projects?filter_country=CTRY_RU&amp;filter_quick=new&amp;pg_num=183" rel="next" class="next_page">Next</a>
        $nodes = $this->finder->query('.//div[@class="browse_pagination"]/a[@class="next_page"]');

        if ($nodes->length != 0) {
            // http://www.indiegogo.com/projects?filter_country=CTRY_xx&filter_quick=new&pg_num=98
            $parts = explode("&", $nodes->item(0)->getAttribute("href"));
            $next_url = "http://".$this->domain() . strstr($parts[0], "?", true). "?" . $parts[1] . "&" . $parts[2];
            return $next_url;
        }
    }
}

?>
