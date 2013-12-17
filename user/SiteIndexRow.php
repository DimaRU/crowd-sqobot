<?php namespace Sqobot;

class SiteIndexRow extends Row {
  static $defaultTable = 'site_index';
  static $fields = array('site_id', 'project_id', 'load_time', 'ref_page');
}
