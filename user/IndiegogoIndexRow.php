<?php namespace Sqobot;

class IndiegogoIndexRow extends Row {
  static $defaultTable = 'indiegogo_index';
  static $fields = array('project_id', 'load_time', 'ref_page');
}
