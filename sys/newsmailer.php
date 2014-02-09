<?php namespace Sqobot;

/* 
 * Copyright (C) 2013 Dmitry Borovikov.
 */

require_once './lib/Twig/Autoloader.php';
require_once './swiftmailer/lib/swift_required.php';

// Form email body and send mail message
class NewsMailer {
    protected $body = "";
    protected $contentlines = 0;

    protected $twig;
    protected $template;
            
  function __construct($templateName) {
    // Create the message
    \Twig_Autoloader::register();
    $loader = new \Twig_Loader_Filesystem(cfg('twigTemplates'));
    $twig = new \Twig_Environment($loader, array(
        'cache' => cfg('twigCache'),
        'auto_reload' => true,
    ));
    $this->template = $twig->loadTemplate($templateName);
  }
  
  // Get the subject block out
  // If the block doesn't exist, use a default
  function renderBlock($block, $param) {
    return $this->template->hasBlock($block)
        ? $this->template->renderBlock($block, $param) 
        : "Block $block does not defined";
  }
  
    function addBodyContent($rows, $block = "body") {
        $this->body .= $this->renderBlock($block, $rows);
        $this->contentlines++;
    }
    
    function addHeader($digest) {
        $this->body = $this->renderBlock("header", $digest) . $this->body;
    }
    
    function addFooter($digest) {
        $this->body .= $this->renderBlock("footer", $digest);
    }
    
    function send($digest, $address, $name) {
        if ($this->contentlines == 0) {
            return;
        }
        $params = compact($digest, $address, $name);
        $this->addHeader($params);
        $this->addFooter($params);
        $message = \Swift_Message::newInstance();
        $message->setBody($this->body, 'text/html');

        $message->setSubject($this->renderBlock("subject", $params));
        $message->setFrom($this->renderBlock("from", $params), $this->renderBlock("fromname", $params));
        $message->setTo(array($address => $name));

        // Create the mail transport configuration
        // TODO: get params from cfg
        $transport = \Swift_SmtpTransport::newInstance('172.16.1.1',25);
        $mailer = \Swift_Mailer::newInstance($transport);
        $result = $mailer->send($message);
        // TODO: add result check
    }
}