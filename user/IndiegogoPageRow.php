<?php namespace Sqobot;

class IndiegogoPageRow extends Row {
  static $defaultTable = 'project_page';
  static $fields = array(
        'site_id',
        'load_time',
        'project_id',
        'name',
        'blurb',
        'goal',
        'campaign_type',
        'country',
        'currency',
        'currency_symbol',
        'deadline',
        'launched_at',
        'creator_name',
        'location',
        'location_url',
        'category',
        'short_url',
        'full_desc',
        'ref_page'
        );
    
}
