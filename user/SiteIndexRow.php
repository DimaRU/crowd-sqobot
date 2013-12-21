<?php namespace Sqobot;

class SiteIndexRow extends Row {
  static $table;
  static $fields = array('site_id', 'project_id', 'load_time', 'ref_page');
}
