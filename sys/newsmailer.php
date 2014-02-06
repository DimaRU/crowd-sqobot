<?php namespace Sqobot;

/* 
 * Copyright (C) 2013 Dmitry Borovikov.
 */

require_once './lib/Twig/Autoloader.php';
require_once './swiftmailer/lib/swift_required.php';

// Form email body and send mail message
class NewsMailer {
    protected $category = "";
    protected $site = "";
    protected $body = "";
    
    protected $contentlines = 0;
    protected $message;
    protected $twig;
            
  function __construct($templateName) {
    // Create the message
    $this->message = \Swift_Message::newInstance();
    \Twig_Autoloader::register();
    $loader = new \Twig_Loader_Filesystem(cfg('twigTemplates'));
    $twig = new \Twig_Environment($loader, array(
        'cache' => cfg('twigCache'),
        'auto_reload' => true,
    ));
    $template = $twig->loadTemplate($templateName);
        // Get the subject block out
        // If the block doesn't exist, use a default
    //    $subject = ($template->hasBlock("subject")
    //        ? $template->renderBlock("subject", array("param1" => $someInformation->foo))
    //        : "Default subject here");
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
    }
    
    function addFooter($digest) {
        $this->body .= "<p>Please do not reply to this message</p>";
    }
    
    function send($digest, $address, $name) {
        if ($this->contentlines == 0) return;

        $this->addSubject(ucfirst($digest)." new projects digest.");
        $this->addHeader($digest);
        $this->addFooter($digest);
        // Create the mail transport configuration
        // TODO: get params from cfg
        $this->transport = \Swift_SmtpTransport::newInstance('172.16.1.1',25);
        $this->message->setBody($this->body, 'text/html');
        $this->message->setFrom("robot@bdm.org.ru", "Crowd scan robot");
        $this->message->setTo(array($address => $name));
        $mailer = \Swift_Mailer::newInstance($this->transport);
        $result = $mailer->send($this->message);
        // TODO: add result check
    }
}