<?php namespace Sqobot;
/* 
 * Copyright (C) 2013 Dmitry.
 *
 */
class TaskMail extends Task {
  static function table($table) {
    return cfg('dbPrefix').$table;
  }

  // Create and send email for this user
  function mail_user($row, $digest) {
    $table_subs = static::table('users_category');
    $id = $row->ID;
    $mailer = new NewsMailer($row->user_email, $row->display_name);
    
    // First of all, get list of subscribed sites
    $sql = "SELECT `site_id` FROM `$table_subs` WHERE `ID`=$id GROUP BY `site_id`";
    $stmt = exec($sql);
    $sites = $stmt->fetchAll();
    $stmt->closeCursor();
    
    foreach($sites as $site) {
        $sitename = $site->site_id;
        // Select new projects gouped by category
        if (!$scancfg = cfg("scan $sitename")) return print "No scan $sitename configuration string".PHP_EOL;
        list($site_index, $site_page, $index_table, $page_table, ) = explode(' ', trim($scancfg));
        $page_table = static::table($page_table);
        
        // Select subsribed categories for site
        $sql = "SELECT {$page_table}.category, {$page_table}.name, {$page_table}.short_url, {$page_table}.blurb\n"
        . "FROM {$table_subs} INNER JOIN {$page_table} ON {$table_subs}.category = {$page_table}.category\n"
        . "WHERE {$page_table}.{$digest}=0 AND {$table_subs}.site_id=\"$sitename\"\n"
        . "ORDER BY {$page_table}.category";
        
        $stmt = exec($sql);
        while ($row = $stmt->fetch()) {
          // Выдаём элементы категории.
          // Для каждой новой категории делаем заголовок
          $mailer->addContentLine($sitename, $row);
        }
        $stmt->closeCursor();
    }
    $mailer->addSubject($digest);
    $mailer->addHeader();
    $mailer->addFooter();
    $mailer->send();
  }
    
  // Email new projects filered by category  
  function do_(array $args = null) {
    if ($args === null) {
      return print 'mail [do ] {hourly|daily|weekly]}';
    }
    $digest = opt(0);
    if (!in_array($digest, array('hourly', 'daily', 'weekly')))
            return print 'Invalid argument. Only hourly|daily|weekly allowed.'. PHP_EOL;
    
    $started = microtime(true);
    

    // Create users list
    $sql = "SELECT wp_users.ID, wp_users.user_email, wp_users.display_name\n"
    . "FROM wp_users";

    $stmt = exec($sql);
    while($row = $stmt->fetch()) {
        // Create and send email for this user
        echo "Email {$row->user_email}", PHP_EOL;
        $this->mail_user($row, $digest);
    }
    $stmt->closeCursor();
    $stmt = null;

    // Всё.
    $duration = microtime(true) - $started;
    
    log("Done emailing $digest digest. ".
         sprintf('This took %1.2f sec.', $duration));

  }
  
}
