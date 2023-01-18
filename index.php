<?php
require __DIR__.'vendor/autoload.php';

$adapter = new Http\Adapter\Guzzle6\Client();
$mailer = new Stampie\Mailer\SendGrid($adapter, 'philipmurray80:helloworld');

// Throws an HttpException for error
// messages not recognized by SendGrid api or ApiException for known errors.
$mailer->send(new Message('lenoxhillpremedical@gmail.com'));
?>