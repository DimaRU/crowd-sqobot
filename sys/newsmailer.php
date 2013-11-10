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
    function addContentLine($sitename, $row) {
        $this->addSiteHeader($sitename);
        $this->addCategoryHeader($row->category);
        
        $s = '<div><a href="' . $row->short_url . '" class="project line" title="'
            . htmlspecialchars($row->blurb) . '">'
            . htmlspecialchars($row->name) . '</a></div>';
        $this->addLine($s);
        $this->contentlines++;
    }
    
    function addSubject($digest) {
        $this->message->setSubject(ucfirst($digest)." new projects digest.");
    }
    
    function addHeader() {
        if ($this->contentlines == 0)
            $this->body = "<h2>No new projects from latest $digest email.</h2>";
    }
    
    function addFooter() {
        $this->body .= "<p>Please do not reply to this message</p>";
    }
    
    function send() {
        $this->message->setBody($this->body, 'text/html');
        $mailer = \Swift_Mailer::newInstance($this->transport);
        $result = $mailer->send($this->message);
        // TODO: add result check
    }
}