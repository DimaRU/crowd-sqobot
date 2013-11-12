<?php namespace Sqobot;

/*
 * Parse Indiegogo index page:
 * "http://www.indiegogo.com/projects?filter_quick=new&pg_num=1"
 * 
 */
class SIndiegogoIndex extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    
    protected function doSlice($data, array $extra) {
        $row = array(
        'load_time' => date(DATE_ATOM),
        'ref_page' => $this->url);

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

        // <div class="project-details"> <a href="/projects/start-anew-world/pinw" class="name bold">Start Anew World</a>
        $finder = new \DomXPath($html);
        $projects_index = $finder->query('.//div[@class="project-details"]/a');

        foreach ($projects_index as $project) {
            $s = $project->getAttribute('href');
            $row['project_id'] = $this->domain() . substr($s, 0, strrpos($s,"/"));
            IndiegogoIndexRow::createOrReplaceWith($row);
        }
        // <div class="browse_pagination" locale="en">
        // <a href="/projects?filter_country=CTRY_RU&amp;filter_quick=new&amp;pg_num=183" rel="next" class="next_page">Next</a>
        $nodes = $finder->query('.//div[@class="browse_pagination"]/a[@class="next_page"]');

        if ($nodes->length != 0) {
            // http://www.indiegogo.com/projects?filter_country=CTRY_xx&filter_quick=new&pg_num=98
            $parts = explode("&", $nodes->item(0)->getAttribute("href"));
            $next_url = "http://".$this->domain() . strstr($parts[0], "?", true). "?" . $parts[1] . "&" . $parts[2];
            unset($finder);
            unset($html);
            return $next_url;
        }
    }
}

?>
