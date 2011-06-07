<?php
/*Copyright (C) 2011 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Artefact2\EligiusStats;

require __DIR__.'/lib.eligius.php';
require __DIR__.'/inc.servers.php';

function showIndividualInstructions() {
	global $SERVERS;

	echo "<h2>Individual statistics</h2>\n<ul>\n";
	foreach($SERVERS as $name => $data) {
		list($prettyName,) = $data;
		echo "<li>If you use the <strong class=\"more\">$prettyName</strong> server : ".
			 "go to <strong class=\"moremore\">http://eligius.st/~artefact2/$name/&lt;your_address&gt;</strong>";
		$success = null;
		$randAddress = cacheFetch('random_address_'.$name, $success);
		if($success) {
			$uri = "http://eligius.st/~artefact2/$name/$randAddress";
			echo "\n<br /><small>Example : <a href=\"$uri\">$uri</a></small>";
		}

		echo "</li>\n";
	}
	echo "</ul>";
}

function showPoolStatuses() {
	global $SERVERS_ADDRESSES;

	echo "<h2>Pool status</h2>\n<table id=\"pool_status\">\n<thead>\n";
	echo "<tr><th>Status</th><th>Address</th><th>Latency</th><th>Uptime <small>(<a href=\"./".DATA_RELATIVE_ROOT."/".STATUS_FILE_NAME."\">API</a>)</small></th></tr>\n";
	echo "</thead>\n<tbody>\n";

	$f = __DIR__.'/'.DATA_RELATIVE_ROOT.'/'.STATUS_FILE_NAME;
	if(file_exists($f)) {
		$data = file_get_contents($f);
		$statuses = json_decode($data, true);
		if(($err = json_last_error()) !== JSON_ERROR_NONE) {
			$statuses = array();
		}
	} else $statuses = array();

	foreach($SERVERS_ADDRESSES as $serverName => $data) {
		list(, $fAddress) = $data;

		if(isset($statuses[$serverName]['status'])) {
			$status = $statuses[$serverName]['status'];

			if(isset($statuses[$serverName]['latency'])) {
				$latency = $statuses[$serverName]['latency'];
				if($latency < 1.0) {
					$latency = "< 1s";
				} else $latency = number_format($latency, 2).' s';
			} else $latency = 'N/A';

			if(isset($statuses[$serverName]['since'])) {
				$uptime = time() - $statuses[$serverName]['since'];
				$uptime = prettyDuration($uptime);
				if($status !== S_WORKING) $uptime = 'Downtime : '.$uptime;
			} else $uptime = 'N/A';

			if(isset($statuses[$serverName]['last-updated'])) {
				$lastUpdated = $statuses[$serverName]['last-updated'];
			}
		} else {
			$latency = 'N/A';
			$status = S_UNKNOWN;
			$uptime = 'N/A';
		}

		if($status == S_UNKNOWN) {
			$status = 'Unknown';
			$color = '#FF6700';
			$textColor = '#000000';
			$uptime = 'N/A';
		} else if($status == S_WORKING) {
			$status = 'Giving work, OK';
			$color = '#93C572';
			$textColor = '#000000';
		} else if($status == S_NETWORK_PROBLEM) {
			$status = 'Network problem (timeout, unreachable)';
			$color = '#E32636';
			$textColor = '#FFFFFF';
		} else if($status == S_INVALID_WORK) {
			$status = 'Invalid or no work given';
			$color = '#E32636';
			$textColor = '#FFFFFF';
		}

		echo "<tr><td style=\"color: $textColor; background-color: $color;\">$status</td><td>$fAddress</td><td>$latency</td><td>$uptime</td></tr>\n";
	}

	echo "</tbody>\n</thead>\n</table>\n";

	if(isset($lastUpdated)) {
		$delay = time() - $lastUpdated;
		if($delay < 60) $delay = 'less than one minute ago';
		else if($delay < 91) $delay = 'one minute ago';
		else $delay = round($delay / 60).' minutes ago';

		echo "<small>This data was last updated $delay.</small>\n";
	}
}

function showRecentBlocks() {
	global $SERVERS;

	echo "<h2>Recently found blocks</h2>\n";
	echo "<ul>\n";
	$now = time();

	foreach($SERVERS as $name => $data) {
		list($prettyName,) = $data;
		echo "<li>By $prettyName :\n<ul>\n";

		$success = null;
		$recent = cacheFetch('recent_blocks_'.$name, $success);
		if($success) {
			foreach($recent as $file => $modificationTime) {
				$interval = prettyDuration($now - $modificationTime, true);
				$block = pathinfo($file, PATHINFO_FILENAME);
				echo "<li><a href=\"http://blockexplorer.com/block/$block\">$block</a>, $interval ago</li>\n";
			}
		} else {
			echo "<em>N/A</em>\n";
		}

		echo "</ul>\n</li>\n";
	}

	echo "</ul>\n";
}

function showTopContributors() {
	global $SERVERS;

	echo "<h2>Top contributors</h2>\n<ul id=\"top_contrib\">\n";

	$success = null;
	$top = cacheFetch('top_contributors', $success);
	$i = 0;
	if($success) {
		foreach($top as $t) {
			++$i;

			$hashrate = $t['hashrate'];
			$address = $t['address'];
			$server = $t['server'];
			
			$hashrate = prettyHashrate($hashrate);
			$pServer = $SERVERS[$server][0];
			$address = str_pad($address, 34, '_', STR_PAD_RIGHT); /* Yes, this is a trick */
			$address = str_replace('_', '&nbsp;', $address);

			echo "<li class=\"rank$i\"><span><a href=\"./$server/$address\">$address</a> on $pServer with $hashrate on average</span></li>\n";
		}
	} else echo "N/A\n";

	echo "</ul>\n";
}

function showContributingInstructions() {
	echo <<<EOT
<h2>Contribute !</h2>
<ul>
<li style="color: darkred;"><strong class="more">How do I start mining on Eligius ?</strong> Read the <a href="http://eligius.st/wiki/index.php/Getting_Started">Getting started page</a> on the wiki.</li>
<li>Contact me (Artefact2) for a statistics-related issue : &lt;a<span>r</span>t<span><span>ef<span>act2</span>@</span>gma</span><span>il.c</span>om&gt;</li>
<li>Join us on IRC for more interactive support : #eligius on irc.freenode.net <a href="http://webchat.freenode.net/?channels=eligius">(chat directly in your browser)</a></li>
<li>Show your support by donating :
<ul>
<li>to <a href="bitcoin:1666R5kdy7qK2RDALPJQ6Wt1czdvn61CQR">1666R5kdy7qK2RDALPJQ6Wt1czdvn61CQR</a> for these stats</li>
<li>to <a href="bitcoin:1RNUbHZwo2PmrEQiuX5ascLEXmtcFpooL">1RNUbHZwo2PmrEQiuX5ascLEXmtcFpooL</a> for the pool</li>
<li>to <a href="bitcoin:16yREn3ixJuPLP1RaLgTjVERsQDhUJgZg">16yREn3ixJuPLP1RaLgTjVERsQDhUJgZg</a> for general website developement</li>
</ul>
</li>
<li>Help us get bigger by promoting the pool !</li>
<li>These stats are released under the <a href="./COPYING">GNU Affero General Public License v3</a> license. You can browse the source online on <a href="https://github.com/Artefact2/eligius-stats">GitHub</a>.
</ul>
EOT;

}

function showPoolHashRate() {
	global $SERVERS;

	echo "<h2>Hashrate</h2>\n<table id=\"pool_hashrate\">\n<thead>\n";

	$total = 0;
	$rates = array();
	foreach($SERVERS as $name => $data) {
		list($pName, $apiRoot) = $data;

		if(file_exists($f = $apiRoot.'/hashrate.txt')) {
			$rates[$pName] = prettyHashrate($rate = file_get_contents($f));
			$total += $rate;
		} else {
			$rates[$pName] = 'N/A';
		}
	}

	$rates['Combined'] = prettyHashrate($total);

	echo "<tr>\n";
	foreach(array_keys($rates) as $s) {
		echo "<th>$s</th>";
	}
	echo "\n</tr>\n</thead>\n<tbody>\n<tr>\n";
	foreach(array_values($rates) as $h) {
		echo "<td>$h</td>";
	}
	echo "\n</tr>\n</tbody>\n</table>\n";
}

function showHashRateGraph() {
	global $SERVERS;

	echo "<div id=\"eligius_pool_hashrate_errors\" class=\"errors\"></div>\n";
	echo "<div id=\"eligius_pool_hashrate\" style=\"width:750px;height:350px;\">You must enable Javascript to see the graph.</div>\n";
	echo "<script type=\"text/javascript\">\n$(function () {\n";
	echo "$('#eligius_pool_hashrate').html('');\nvar series = [];\n";
	echo <<<EOT
var options = {
	legend: { position: "nw" },
	xaxis: { mode: "time" },
	yaxis: { position: "right", min: 0, tickFormatter: EligiusUtils.formatHashrate },
	series: { lines: { fill: 0.3 } }
};

EOT;

	foreach($SERVERS as $name => $data) {
		list($prettyName,) = $data;
		$color = extractColor($prettyName);
		$uri = './'.DATA_RELATIVE_ROOT.'/'.T_HASHRATE_POOL.'_'.$name.DATA_SUFFIX;
		echo <<<EOT
$.get("$uri", "", function(data, textStatus, xhr) {
	series.push({ data: data, label: "$prettyName", color: "$color" });
	$.plot($('#eligius_pool_hashrate'), series, options);
}, "json").error(function() {
	$('#eligius_pool_hashrate_errors').append('<p>An error happened while loading the data for the $prettyName server.<br />Try reloading the page.</p>');
});

EOT;
	}

	echo "});\n</script>\n";
}

if($_SERVER['QUERY_STRING'] !== "dispatch_request") {
	header('HTTP/1.1 404 Not Found', true, 404);
	die;
}

echo <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<link type="text/css" rel="stylesheet" href="./web.theme.css">
<!--[if lte IE 8]><script type="text/javascript" src="./flot/excanvas.min.js"></script><![endif]-->
<script type="text/javascript" src="./lib.util.js"></script>
<script type="text/javascript" src="./flot/jquery.js"></script>
<script type="text/javascript" src="./flot/jquery.flot.js"></script>
<title>Eligius pool statistics</title>
</head>
<body>

EOT;
echo "<h1>Eligius pool statistics <small>(version ".VERSION."!)</small></h1>\n";


showIndividualInstructions();
showPoolStatuses();
showRecentBlocks();
showPoolHashRate();
showHashRateGraph();
showTopContributors();
showContributingInstructions();

if(file_exists(__DIR__.'/inc.analytics.php')) {
	require __DIR__.'/inc.analytics.php';
}

echo "</body>\n</html>\n";
die;