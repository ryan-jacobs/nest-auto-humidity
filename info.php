<?php

use rjacobs\NestAutoHumidity\Poller;

require_once('autoloader.php');

try {
  $poller = Poller::create();
}
catch (Exception $exc) {
  print '<p>Nest API Connection could not be established. Please your connection and configured credentials.</p>';
}
$info = $poller->info();
$date = date(DATE_RSS);
?>
<h2>State Summary</h2>
<p>The following raw data has been dumped from polling calculations on <?php print $date ?></p>
<pre><?php print_r($info) ?></pre>