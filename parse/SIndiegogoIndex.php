<?php namespace Sqobot;

/*
 * Parse Indiegogo index page:
 * "http://www.indiegogo.com/projects?filter_quick=new&pg_num=1"
 * 
 */
class SIndiegogoIndex extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    const PROJ_MARK = '<a href="/projects/';
    
    protected function startParse() {
        $this->loadURL($this->url, array('accept' => "text/html"), array(&$this,'parseData'));
    }
    
    function parseData($httpReturnCode, $data, $httpMovedURL) {
        if ($httpReturnCode == 404) {
            return;
        }

        $this->row = array(
        'site_id' => 'indiegogo',
        'load_time' => date(DATE_ATOM),
        'ref_page' => $this->url);
        Row::setTableNameKey($this->getopt('index_table'));

        // <a href="/projects/donduffie-productions/pinw" class="i-project">
        $pos1 = 0;
        while($pos1 = strpos($data, self::PROJ_MARK, $pos1)) {
            $pos1 += strlen(self::PROJ_MARK);
            $pos2 = strpos($data, '/pinw', $pos1);
            $this->row['project_id'] = "www.indiegogo.com/projects/" . substr($data, $pos1, $pos2 - $pos1);
            Row::createOrReplaceWith($this->row);
        }
        return false;
    }
}
