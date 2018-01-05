<?php

use rjacobs\NestAutoHumidity\Poller;

require_once('autoloader.php');

$poller = Poller::create();
$poller->poll();
exit();