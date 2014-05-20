<?php namespace Sqobot;

/*
 * Parse Indiegogo index page:
 * "http://www.indiegogo.com/projects?filter_quick=new&pg_num=1"
 * 
 */
class SIndiegogoIndex extends Sqissor {
    static $domain_name = 'www.indiegogo.com';
    const PROJ_MARK = '<div class="i-img" data-src="https://images.indiegogo.com/projects/';
    
    protected function doSlice($url) {
        $data = $this->loadURL($url, array('accept' => "text/html"));

        if (Download::httpReturnCode() == 404)
            return;
        
        $row = array(
        'site_id' => 'indiegogo',
        'load_time' => date(DATE_ATOM),
        'ref_page' => $this->url);
        Row::setTableName($this->getopt('index_table'));

        //     <div class="i-img" data-src="https://images.indiegogo.com/projects/766558/pictures/new_baseball/20140425161938-profile-pic.jpg?1398467986">
        $pos1 = 0;
        while($pos1 = strpos($data, self::PROJ_MARK, $pos1)) {
            $pos1 += strlen(self::PROJ_MARK);
            $pos2 = strpos($data, "/", $pos1);
            $row['project_id'] = "www.indiegogo.com/projects/" . substr($data, $pos1, $pos2 - $pos1);
            Row::createOrReplaceWith($row);
        }
        return false;
    }
}
