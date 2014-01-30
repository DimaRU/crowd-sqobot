<?php namespace Sqobot;
/* 
 * Copyright (C) 2013 Dmitry.
 *
 */
class TaskMail extends Task {
  static $page_table = 'project_page';
  static $subs_table = 'users_category';
  static $newmail_table = 'newmail_table';
  
  static function table($table) {
    return cfg('dbPrefix').$table;
  }
  

  // Create and send email for this user
  function mail_user($row, $digest) {
    $subs_table = static::table(static::$subs_table);
    $page_table = static::table(static::$page_table);
    $newmail_table = static::table(static::$newmail_table);
    
    $id = $row->ID;
    $mailer = new NewsMailer($row->user_email, $row->display_name);
    
    // Select subsribed sities and categories for site
    $sql = "SELECT $page_table.site_id, $page_table.category, $page_table.name, $page_table.short_url, $page_table.blurb\n"
    . "FROM ($subs_table INNER JOIN $page_table ON ($subs_table.category = $page_table.category) "
    . "AND ($subs_table.site_id = $page_table.site_id)) INNER JOIN $newmail_table ON $page_table.project_id = $newmail_table.project_id\n"
    . "WHERE $subs_table.ID = $id AND $newmail_table.digest = \"$digest\"\n"
    . "ORDER BY $page_table.site_id, $page_table.category";
    $stmt = exec($sql);
    
    while ($row = $stmt->fetch()) {
      // Выдаём элементы категории.
      // Для каждой новой категории делаем заголовок
      $mailer->addContentLine($row);
    }
    $stmt->closeCursor();

    if ($mailer->getContentLines() == 0) return;
    
    $mailer->addSubject(ucfirst($digest)." new projects digest.");
    $mailer->addHeader($digest);
    $mailer->addFooter($digest);
    $mailer->send();
  }
    
  // Email new projects filered by category  
  function do_new(array $args = null) {
    $subs_table = static::table(static::$subs_table);
    $page_table = static::table(static::$page_table);
    $newmail_table = static::table(static::$newmail_table);

    if ($args === null) {
      return print 'mail [do ] {hourly|daily|weekly]}';
    }
    $digest = opt(0);
    if (!in_array($digest, array('hourly', 'daily', 'weekly')))
            return print 'Invalid argument. Only hourly|daily|weekly allowed.'. PHP_EOL;
    
    echo "Emailing $digest new projects", PHP_EOL;

    $started = microtime(true);
    
    // Create unmailed projects snapshot
    $sql = "INSERT INTO $newmail_table (project_id, digest )\n"
        . "SELECT $page_table.project_id, \"$digest\"\n"
        . "FROM $page_table\n"
        . "WHERE $page_table.$digest=0";
    $stmt = exec($sql);
    
    // Create users list
    $sql = "SELECT wp_users.ID, wp_users.user_email, wp_users.display_name FROM wp_users WHERE wp_users.digest = \"$digest\"";
    $stmt = exec($sql);

    while($row = $stmt->fetch()) {
        // Create and send email for this user
        log("Email {$row->user_email}");
        $this->mail_user($row, $digest);
    }
    $stmt->closeCursor();

    // Mark mailed
    $sql = "UPDATE $page_table INNER JOIN $newmail_table ON $page_table.project_id = $newmail_table.project_id SET $page_table.$digest = 1\n"
        . "WHERE $newmail_table.digest = \"$digest\"";
    $stmt = exec($sql);

    // Delete work
    $sql = "DELETE FROM $newmail_table WHERE $newmail_table.digest = \"$digest\"";    
    $stmt = exec($sql);
    
    // Всё.
    $duration = microtime(true) - $started;

    log("Done emailing $digest digest. ".
         sprintf('This took %1.2f sec.', $duration));
  }

  function do_stat(array $args = null) {
    $page_table = static::table(static::$page_table);

    if ($args === null or !opt(1)) {
      return print 'mail stat {hourly|daily|weekly] EMAIL}';
    }
    $digest = opt(0);
    $email = opt(1);
    
    if (!in_array($digest, array('hourly', 'daily', 'weekly')))
            return print 'Invalid argument. Only hourly|daily|weekly allowed.'. PHP_EOL;
    
    $mailer = new NewsMailer($email, "");

    $sql = "SELECT $page_table.site_id, $page_table.category, Count($page_table.category) AS `count`\n"
    . "FROM $page_table\n"
    . "WHERE $page_table.$digest = 0\n"
    . "GROUP BY $page_table.site_id, $page_table.category";
    $stmt = exec($sql);
    $stats = $stmt->fetchAll();

    foreach ($stats as $row) {
      $mailer->addStatLine($row);
    }
    $mailer->addSubject(ucfirst($digest)." stats by site and category.");
    $mailer->addHeader($digest);
    $mailer->addFooter($digest);
    $mailer->send();
  }  
}
