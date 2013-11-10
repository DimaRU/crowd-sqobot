<?php namespace Sqobot;

class KickstarterPageRow extends Row {
  static $defaultTable = 'kickstarter_pages';
  static $fields = array(
        'site_id',
        'load_time',
        'project_id',
        'name',
        'blurb',
        'goal',
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
        'ref_page'
        );
    
}
