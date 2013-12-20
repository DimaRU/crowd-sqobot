<?php namespace Sqobot;

class ProjectPageRow extends Row {
  static $defaultTable = 'project_page';
  static $fields = array(
        'site_id',
        'load_time',
        'project_id',
        'name',
        'blurb',
        'avatar',
        'goal',
        'campaign_type',
        'country',
        'currency',
        'currency_symbol',
        'currency_trailing_code',
        'deadline',
        'launched_at',
        'creator_name',
        'location',
        'location_url',
        'latitude',
        'longitude',
        'category',
        'short_url',
        'full_desc',
        'project_json',
        'ref_page',
        'mailformed'
        );
  function defaults() {
    foreach (static::$fields as $field) { $this->$field = 0; }
    return $this;
  }
}
