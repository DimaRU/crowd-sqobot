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

        $this->initDom($data);

        $projects_index = $this->querySafe('.//div[@class="project-thumbnail"]/a');

        foreach ($projects_index as $project) {
            $row['project_id'] = $this->domain() . strstr($project->getAttribute('href'), "?", true);
            KickstarterIndexRow::createIgnoreWith($row);
        }
        $last_page = $this->queryAttribute('.//li[@class="page"]', "data-last_page");

        if ($last_page == "false") {
            // <a rel="next" href="/discover/recently-launched?page=2">2</a>
            $next_url = "http://". $this->domain() . $this->queryAttribute('.//a[@rel="next"]', "href");
            return $next_url;
        }
    }
}

?>