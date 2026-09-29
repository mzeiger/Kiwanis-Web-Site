<?php

include 'Mail.php';
require_once 'Mail_Mime.php' ;

$text = 'Text version of email';
$html = '<html><body>HTML version of email</body></html>';
$file = '/home/richard/example.php';
$crlf = "\n";
$hdrs = array(
              'From'    => 'zm8032@gmail.com',
              'Subject' => 'Test mime message'
              );

try
{
    $mime = new Mail_mime(array('eol' => $crlf));

    $mime->setTXTBody($text);
    $mime->setHTMLBody($html);
    $mime->addAttachment($file, 'text/plain');

    $body = $mime->get();
    $hdrs = $mime->headers($hdrs);

    $mail =& Mail::factory('mail');
    $mail->send('mark.zeiger@gmail.com', $hdrs, $body);
}
catch (Exception $ex)
{
    echo $ex->getMessage();
}
?>
