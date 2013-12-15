<?php namespace Sqobot;

/* 
 * Copyright (C) 2013 Dmitry Borovikov.
 */

// Form email body and send mail message
class NewsMailer {
    protected $category = "";
    protected $site = "";
    protected $body = "";
    
    protected $contentlines = 0;
    protected $transport;
    protected $message;
            
  function __construct($address, $name) {
    // Create the mail transport configuration
    // TODO: get params from cfg
    $this->transport = \Swift_SmtpTransport::newInstance('172.16.1.1',25);
    // Create the message
    $this->message = \Swift_Message::newInstance();
    
    $this->message->setFrom("robot@bdm.org.ru", "Crowd scan robot");
    $this->message->setTo(array($address => $name));
    
  }
  function getContentLines() {
      return $this->contentlines;
  }
    //
    function addLine($s) {
        $this->body .= $s . PHP_EOL;
    }
  
    // Add site Header
    protected function addSiteHeader($sitename) {
        if ($this->site != $sitename){
            // <h1>Kickstarter</h1>
            $this->addLine("<h2>".ucfirst($sitename)."</h2>");
            $this->site = $sitename;
            $this->category = "";   // Reset category
        }
    }

    // Add category header
    protected function addCategoryHeader($category) {
        if ($this->category != $category) {
            $this->addLine("<h3>$category</h3>");
            $this->category = $category;
        }
    }
    
    // Add one line with content (ref, name ...)
    function addContentLine($row) {
        $this->addSiteHeader($row->site_id);
        $this->addCategoryHeader($row->category);
        
        $s = '<div><a href="' . $row->short_url . '" class="project line" title="'
            . htmlspecialchars($row->blurb) . '">'
            . htmlspecialchars($row->name) . '</a></div>';
        $this->addLine($s);
        $this->contentlines++;
    }

    // Add one line with stats (category, count)
    function addStatLine($row) {
        $this->addSiteHeader($row->site_id);
        
        $s = '<div><span style="width: 140px; float: left;">'.$row->category.'&nbsp;</span>'.$row->count.'</div>';
        $this->addLine($s);
        $this->contentlines++;
    }
    
    function addSubject($subject) {
        $this->message->setSubject($subject);
    }
    
    function addHeader($digest) {
        if ($this->contentlines == 0)
            $this->body = "<h2>No new projects from latest $digest email.</h2>";
    }
    
    function addFooter($digest) {
        $this->body .= "<p>Please do not reply to this message</p>";
    }
    
    function send() {
        $this->message->setBody($this->body, 'text/html');
        $mailer = \Swift_Mailer::newInstance($this->transport);
        $result = $mailer->send($this->message);
        // TODO: add result check
    }
}