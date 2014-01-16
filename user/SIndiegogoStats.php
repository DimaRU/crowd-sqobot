<?php namespace Sqobot;

/*
 * Parse Indiegogo json project page:
 * "http://www.kickstarter.com/project/.......
 * 
 */
class SIndiegogoStats extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    static $accept = "application/json";
    
    protected function doSlice($data, array $options) {
        $row = array('site_id' => 'indiegogo', 
                     'load_time' => date(DATE_ATOM),
                     'project_id' => strstr($this->url, $this->domain()) );

        // Trace project remove
        if (Download::httpReturnCode() == 404) {
            $upd['state'] = "404";
            $where['project_id'] = $row['project_id'];
            Row::setTableName($options['page_table']);
            Row::updateIgnoreWith($upd, $where);
            return;
        }

        // Project rename
        if (($newurl = Download::httpMovedURL()) !== false) {
            // Mark old project page
            warn("Renamed ".$this->url);
            $upd['state'] = "Renamed";
            $where['project_id'] = $row['project_id'];
            Row::setTableName($options['page_table']);
            Row::updateIgnoreWith($upd, $where);
            // Rescan project
            $idx = $row;
            $idx['project_id'] = strstr($newurl, $this->domain());  // new
            $idx['ref_page'] = $row['project_id'];                  // old
            Row::setTableName($options['index_table']);
            Row::createOrReplaceWith($idx);
            // Move stats to new name, if any
            $upd1['project_id'] = $idx['project_id'];
            Row::setTableName($options['stats_table']);
            Row::updateIgnoreWith($upd1, $where);
            $row['project_id'] = $idx['project_id'];
            return;
        }

        $pdata = json_decode($data, true);
        //$row['project_json'] = $data;
        $row['pledged'] = $pdata['balance'];
        $row['backers_count'] = $pdata['funders'];
        $row['comments_count'] = $pdata['comments'];
        //$row['updates_count'] = $pdata['updates_count'];
        Row::setTableName($options['stats_table']);
        Row::createWith($row);
    }
}
