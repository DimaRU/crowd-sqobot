<?php namespace Sqobot;

class KickstarterIndexRow extends Row {
  static $defaultTable = 'kickstarter_index';
  static $fields = array('project_id', 'load_time', 'ref_page');
}
