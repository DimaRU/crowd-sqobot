<?php namespace Sqobot;

/*
 * Parse Kickstarter index page:
 * "http://www.kickstarter.com/discover/recently-launched?page=1"
 * 
 */
class SKickstarterIndex extends Sqissor {
    static $domain_name = 'www.kickstarter.com';

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

        $html = new \DOMDocument();
        $html->validateOnParse = true;
        $cur = libxml_use_internal_errors(true);
        $html->loadHTML($clean_data);
        libxml_use_internal_errors($cur);

        $finder = new \DomXPath($html);
        $projects_index = $finder->query('.//div[@class="project-thumbnail"]/a');

        foreach ($projects_index as $project) {
            $row['project_id'] = $this->domain() . strstr($project->getAttribute('href'), "?", true);
            KickstarterIndexRow::createIgnoreWith($row);
        }
        $nodes = $finder->query('.//li[@class="page"]');
        $last_page = $nodes->item(0)->getAttribute("data-last_page");

        if ($last_page == "false") {
            // <a rel="next" href="/discover/recently-launched?page=2">2</a>
            $nodes = $finder->query('.//a[@rel="next"]');
            $next_url = "http://". $this->domain() . $nodes->item(0)->getAttribute("href");
            return $next_url;
        }
    }
}

?>