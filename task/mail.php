<?php namespace Sqobot;
/* 
 * Copyright (C) 2013 Dmitry.
 *
 */
class TaskMail extends Task {

  protected function mailFields($page_table) {
    $mailFields = explode(" ", cfg("mailFields"));
    array_walk($mailFields, function(&$s) use ($page_table) {
               $s = $page_table. "." . $s;
               });
    return join(', ', $mailFields);
}

// Create and send email for this user
  function mail_user($user, $digest) {
    $id = $user->ID;
    $mailer = new NewsMailer('Projectemail.html.twig');
    
    $dbNames = cfgDbOptions('dbNames');
    $dbComNames = cfgDbOptions('dbComNames');
    extract($dbComNames['common']);        // subs_table newmail_table
    
    foreach ($dbNames as $site_id => $dbNameList) {
        extract($dbNameList);
        $mailFields = $this->mailFields($page_table);
        // Select subsribed sities and categories for site
        $sql = "SELECT ".$mailFields. "\n"
        . "FROM ($subs_table INNER JOIN $page_table ON ($subs_table.category = $page_table.category) "
        . "AND ($subs_table.site_id = $page_table.site_id)) INNER JOIN $newmail_table ON $page_table.project_id = $newmail_table.project_id\n"
        . "WHERE $page_table.site_id = \"$site_id\" AND "
                . "$page_table.mailformed = 0 AND "
                . "$page_table.state IS NULL AND "
                . "$subs_table.ID = $id AND "
                . "$newmail_table.digest = \"$digest\"\n"
        . "ORDER BY $mailFields";
        $stmt = exec($sql);
        //echo $stmt->rowCount(),PHP_EOL; 
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();

        if (count($rows)) {
            $mailer->addBlockContent($site_id, array('rows' => $rows));
        }
    }

    $mailer->send($digest, $user->user_email, $user->display_name);
  }

  // Email new projects filered by category  
  function do_news(array $args = null) {
    if ($args === null) {
      return print 'mail news hourly|daily|weekly [--no-mark]}';
    }
    $digest = opt(0);
    if (!in_array($digest, array('hourly', 'daily', 'weekly'))) {
            return print 'Invalid argument. Only hourly|daily|weekly allowed.'. PHP_EOL;
    }
    
    echo "Emailing $digest new projects", PHP_EOL;

    $dbNames = cfgDbOptions('dbNames');
    $dbComNames = cfgDbOptions('dbComNames');
    extract($dbComNames['common']);        // subs_table newmail_table
    
    foreach ($dbNames as $site_id => $dbNameList) {
        extract($dbNameList);
        // Create unmailed projects snapshot
        $sql = "INSERT IGNORE INTO $newmail_table (project_id, site_id, digest )\n"
            . "SELECT project_id, site_id, \"$digest\"\n"
            . "FROM $page_table\n"
            . "WHERE $page_table.$digest=0 AND $page_table.site_id = \"$site_id\"";
        $stmt = exec($sql);
    }
    
    // Create users list
    $sql = "SELECT wp_users.ID, wp_users.user_email, wp_users.display_name FROM wp_users WHERE wp_users.digest = \"$digest\"";
    $stmt = exec($sql);

    while($user = $stmt->fetch()) {
        // Create and send email for this user
        log("Email {$user->user_email}");
        $this->mail_user($user, $digest);
    }
    $stmt->closeCursor();

    foreach ($dbNames as $site_id => $dbNameList) {
        extract($dbNameList);
        if (empty($args['no-mark'])) {
            // Mark mailed
            $sql = "UPDATE $page_table INNER JOIN $newmail_table ON $page_table.project_id = $newmail_table.project_id SET $page_table.$digest = 1\n"
                . "WHERE $newmail_table.digest = \"$digest\" AND $newmail_table.site_id = \"$site_id\"";
            $stmt = exec($sql);
        }    

        // Delete work
        $sql = "DELETE FROM $newmail_table WHERE $newmail_table.digest = \"$digest\" AND $newmail_table.site_id = \"$site_id\"";    
        $stmt = exec($sql);
    }
    // Всё.

    log("Done emailing $digest digest. ");
  }
  
/*
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
    $stmt->closeCursor();

    // Add one line with stats (category, count)
    function addStatLine($row) {
        $this->addSiteHeader($row->site_id);
        
        $s = '<div><span style="width: 140px; float: left;">'.$row->category.'&nbsp;</span>'.$row->count.'</div>';
        $this->addLine($s);
        $this->contentlines++;
    }

    foreach ($stats as $row) {
      $mailer->addStatLine($row);
    }
    $mailer->addSubject(ucfirst($digest)." stats by site and category.");
    $mailer->addHeader($digest);
    $mailer->addFooter($digest);
    $mailer->send();
  }  
 */
}
