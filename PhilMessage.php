<?php

// composer autoloading.
require 'vendor/autoload.php';

class Message extends \Stampie\Message
{
	public function getFrom() { return 'philipmurray80@gmail.com'; }
	public function getSubject() { return 'You are trying out Stampie'; }
	public function getText() { return 'So what do you think about it?'; }
}

?>