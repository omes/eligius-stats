<?php
/* Copyright (C) 2011  Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>. */
?><!DOCTYPE html> 
<html> 
<head>
<title>Eligius pool statistics</title> 
<meta charset="utf-8">
</head>
<body style="font-family: monospace; font-size: 0.9em;"> 

<h2>Eligius pool statistics</h2> 

<p>To see individual statistics :</p>
<ul><?php

require __DIR__.'/servers.inc.php';

foreach($SERVERS as $shortName => $data) {
	list($fullName, $apiRoot) = $data;
	$example = file_get_contents(__DIR__.'/cache/'.$shortName);
	echo "<li>if you use the <strong style=\"font-size: 1.1em;\">$fullName</strong> server : go to <strong style=\"font-size: 1.2em;\">http://eligius.st/~artefact2/$shortName/&lt;your_address&gt;.htm</strong><br /><small>Example : <a href=\"http://eligius.st/~artefact2/$shortName/$example\">http://eligius.st/~artefact2/$shortName/$example</a></small></li>\n";
}

?></ul>

<h2>Pool status</h2>
<?php

$delay = time() - filemtime($f = __DIR__.'/cache/status');

$delay = floor($delay / 60);
if($delay == 0) $hDelay = 'less than one minute ';
else if($delay == 1) $hDelay = 'one minute';
else if($delay == 2) $hDelay = 'two minutes';
else if($delay < 10) $hDelay = $delay.' minutes';
else $abort = true;

if(isset($abort) && $abort) {
	echo "<p>Pool status unavailible.</p>";
} else {
	require $f;
	echo "<p><small>This data was last refreshed $hDelay ago.</small></p>\n";
}

?>

<h2>Recently found blocks</h2>
<ul><?php

foreach($SERVERS as $shortName => $data) {
        list($fullName, $apiRoot) = $data;
	echo "<li>by $fullName :";
	echo file_get_contents(__DIR__.'/cache/'.$shortName.'_blocks');
	echo "</li>";
}

?></ul>

<p><small>(The UTC timezone is used for dates.)</small></p>

<h2>Current status</h2>
<?php

$combined = floatval(file_get_contents('/var/lib/eligius/combined/hashrate.txt'));
$eu = floatval(file_get_contents('/var/lib/eligius/eu/hashrate.txt'));
$us = floatval(file_get_contents('/var/lib/eligius/us/hashrate.txt'));

$hash = max($combined, $eu + $us);
$hash = number_format($hash / 1000000000, 2);

?>
<p>The two pools, combined, are currently doing <?php echo $hash; ?> Ghashes/sec.</p>

<p>
<img src="./cache/__hashrate" alt="Pool hashrate graph" />
</p>

<h2>Top contributors</h2>
<?php

require __DIR__.'/cache/top_contrib';

?>

<h2>Contribute !</h2>
<ul>
<li>Contact me : &lt;arte<span></span>fa<span>ct2</span><strong>@</strong>gmail<span>.c</span>om&gt; (For a stats-related problem only ! Contact luke-jr for a pool-related issue.)</li>
<li>Donate to : <ul>
	<li>1666R5kdy7qK2RDALPJQ6Wt1czdvn61CQR for these stats</li>
	<li>1RNUbHZwo2PmrEQiuX5ascLEXmtcFpooL for the pool </li>
	<li>16yREn3ixJuPLP1RaLgTjVERsQDhUJgZg for general website developement</li>
</ul></li>
</ul>

<?php

echo file_get_contents(__DIR__.'/analytics.inc.php');

?>
</body> 
</html>
