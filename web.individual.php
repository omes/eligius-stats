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

function makeTicks() {
	$now = time();
	$now -= $now % 86400;
	$threshold = $now - TIMESPAN_SHORT + 1;

	$ticks = array();
	while($now > $threshold) {
		$ticks[] = bcmul($now, 1000, 0);
		$now -= 86400;
	}

	$ticks = array_reverse($ticks);
	return '['.implode(', ', $ticks).']';
}

function showFooter() {
	echo <<<EOT
<hr />
<p><a href="../">&larr; Get back to the main page</a></p>
EOT;

}

function showHashrateAverage($server, $address) {
	$success = null;
	$averages = cacheFetch('average_hashrates', $success);

	if($success && isset($averages[$server][$address])) {
		$rate = prettyHashrate($averages[$server][$address]);
		echo "<h2>Hashrate</h2>\n<p>This user is contributing to the pool by doing, in average, <strong class=\"moremore\">$rate</strong>. This is a 3-hour average, and may or may not reflect your real hashrate, depending on luck.</p>\n";
	} else if($success) {
		echo "<h2>Hashrate</h2>\n<p>This user has not contributed a share in the last three hours.</p>\n";
	} else {
		echo "<h2>Hashrate</h2>\n<p>The averages are not available at the moment. Try later !</p>\n";
	}
}

function showBalance($unpaid, $current) {
	echo <<<EOT
<h2>Balance</h2>
<ul>
<li>Unpaid reward : <strong class="moremore">$unpaid BTC</strong><br />
This is the reward you earned by contributing to the previous blocks. This amount will be paid to you when it reaches 1 BTC or after one week of inactivity, when the pool finds a block.</li>
<li>Current block estimate : <strong class="moremore">$current BTC</strong><br />
This is an <strong>estimation</strong> of the reward that you will earn when the pool finds the block it is currently working on. It can fluctuate over time, if your contribution relative to the pool's size changes. So, don't panic if it goes down a little !</li>
</ul>
EOT;
}

function showBalanceGraph($server, $address) {
	$paidUri = '../'.DATA_RELATIVE_ROOT.'/'.T_BALANCE_ALREADY_PAID.'_'.$server.'_'.$address.DATA_SUFFIX;
	$unpaidUri = '../'.DATA_RELATIVE_ROOT.'/'.T_BALANCE_UNPAID_REWARD.'_'.$server.'_'.$address.DATA_SUFFIX;
	$currentUri = '../'.DATA_RELATIVE_ROOT.'/'.T_BALANCE_CURRENT_BLOCK.'_'.$server.'_'.$address.DATA_SUFFIX;
	$ticks = makeTicks();

	echo "<div class=\"graph\">\n<div id=\"eligius_balance_errors\" class=\"errors\"></div>\n";
	echo "<div id=\"eligius_balance\" style=\"width:700px;height:350px;\">You must enable Javascript to see the graph.</div>\n</div>\n";
	echo "<script type=\"text/javascript\">\n$(function () {\n";
	echo "$('#eligius_balance').html('');\nvar series = [];\n";
	echo <<<EOT
var options = {
	legend: { position: "nw" },
	xaxis: { mode: "time", ticks: $ticks },
	yaxis: { position: "right", tickFormatter: EligiusUtils.formatBTC },
	series: { lines: { fill: 0.3, steps: true }, stack: true }
};

$.get("$paidUri", "", function(data, textStatus, xhr) {
	options.yaxis.min = data[0][1];
	series.push({ data: data, label: "Already paid", color: "#062270" });
	$.plot($('#eligius_balance'), series, options);

	$.get("$unpaidUri", "", function(data, textStatus, xhr) {
		series.push({ data: data, label: "Unpaid reward", color: "#6D89D5" });
		$.plot($('#eligius_balance'), series, options);

		$.get("$currentUri", "", function(data, textStatus, xhr) {
			series.push({ data: data, label: "Current block estimate", color: "#FFE040" });
			$.plot($('#eligius_balance'), series, options);
		}, "json").error(function() {
			$('#eligius_balance_errors').append('<p>An error happened while loading the "current block estimate" data.<br />Try reloading the page.</p>');
		});
		
	}, "json").error(function() {
		$('#eligius_balance_errors').append('<p>An error happened while loading the "unpaid reward" data.<br />Try reloading the page.</p>');
	});

}, "json").error(function() {
	$('#eligius_balance_errors').append('<p>An error happened while loading the "already paid" data.<br />Try reloading the page.</p>');
});
EOT;

	echo "});\n</script>\n";
}

function showHashRateGraph($server, $address) {
	$uri = '../'.DATA_RELATIVE_ROOT.'/'.T_HASHRATE_INDIVIDUAL.'_'.$server.'_'.$address.DATA_SUFFIX;
	$ticks = makeTicks();

	echo "<div class=\"graph\">\n<div id=\"eligius_indiv_hashrate_errors\" class=\"errors\"></div>\n";
	echo "<div id=\"eligius_indiv_hashrate\" style=\"width:750px;height:350px;\">You must enable Javascript to see the graph.</div>\n</div>\n";
	echo "<script type=\"text/javascript\">\n$(function () {\n";
	echo "$('#eligius_indiv_hashrate').html('');\nvar series = [];\n";
	echo <<<EOT
var options = {
	legend: { position: "nw" },
	xaxis: { mode: "time", ticks: $ticks },
	yaxis: { position: "right", tickFormatter: EligiusUtils.formatHashrate },
	series: { lines: { fill: 0.3 }, stack: true }
};

$.get("$uri", "", function(data, textStatus, xhr) {
	series.push({ data: data, label: "Hashrate", color: "#D70A53" });
	$.plot($('#eligius_indiv_hashrate'), series, options);
}, "json").error(function() {
	$('#eligius_indiv_hashrate_errors').append('<p>An error happened while loading the hashrate data.<br />Try reloading the page.</p>');
});
EOT;

	echo "});\n</script>\n";
}

$uri = explode('/', $_SERVER['REQUEST_URI']);
$address = array_pop($uri);
$server = array_pop($uri);

if(preg_match('%\.htm$%Di', $address)) {
	$newUri = preg_replace('%(\.htm)$%Di', '', $_SERVER['REQUEST_URI']);
	header('Location: '.$newUri);
	die;
}

if(!isset($SERVERS[$server])) {
	header('HTTP/1.1 404 Not Found', true, 404);
	echo <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<link type="text/css" rel="stylesheet" href="../web.theme.css">
</head>
<body>
<h1>Unknown server !</h1>
EOT;
	showFooter();
	echo "</body>\n</html>\n";
	die;
}

list($prettyName, $apiRoot) = $SERVERS[$server];
$addresses = getActiveAddresses($apiRoot);

list(, $unpaid, $current) = getBalance($apiRoot, $address);
$total = bcadd($unpaid, $current, 8).' BTC';

if(!in_array($address, $addresses)) {
	header('HTTP/1.1 404 Not Found', true, 404);
	echo <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<link type="text/css" rel="stylesheet" href="../web.theme.css">
</head>
<body>
<h1>Unknown address !</h1>
<p>Sorry man, I have no data for this address. Here is maybe why :</p>
<ul>
<li>There is a typo in your address. Make sure <strong>$address</strong> is a valid Bitcoin address, and is yours !</li>
<li>You just started mining. Give it a few minutes, and the stats will show up.</li>
<li>You haven't mined for a week. No stats for you !</li>
<li>There is a problem with the API (very unlikely). If the problem persists, and you're aware of all all the text above, then join us on IRC for help (the link is at the bottom of the main page).</li>
</ul>
EOT;
	showFooter();
	echo "</body>\n</html>\n";
	die;
}

echo <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<link type="text/css" rel="stylesheet" href="../web.theme.css">
<!--[if lte IE 8]><script type="text/javascript" src="../flot/excanvas.min.js"></script><![endif]-->
<script type="text/javascript" src="../lib.util.js"></script>
<script type="text/javascript" src="../flot/jquery.js"></script>
<script type="text/javascript" src="../flot/jquery.flot.js"></script>
<script type="text/javascript" src="../flot/jquery.flot.stack.js"></script>
<title>($total) $address on $prettyName - Eligius pool</title>
</head>
<body>

EOT;

echo "<h1>$address on $prettyName</h1>\n";

showBalance($unpaid, $current);
showHashrateAverage($server, $address);
echo "<h2>Graphs</h2>\n";
showBalanceGraph($server, $address);
showHashRateGraph($server, $address);

showFooter();

if(file_exists(__DIR__.'/inc.analytics.php')) {
	require __DIR__.'/inc.analytics.php';
}

echo "</body>\n</html>\n";
die;