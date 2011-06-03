#!/usr/bin/env php
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

define('TIMESPAN', 7 * 86400);
define('STEP', 300);
define('HEARTBEAT', 600);
define('STEPS', 3);
define('ROWS', 672);

assert('STEP * STEPS * ROWS == TIMESPAN');

define('HASHRATE_AVG_PERIOD', 600);
define('HASHRATE_LAG', 300);

define('TIMESPAN_LONG', 31 * 86400);
define('STEP_LONG', STEP);
define('HEARTBEAT_LONG', HEARTBEAT);
define('STEPS_LONG', STEPS);
define('ROWS_LONG', 2976); /* ROWS * (31 / 7) */

assert('STEP_LONG * STEPS_LONG * ROWS_LONG == TIMESPAN_LONG');

define('RECENT_BLOCKS', 7);
define('TOP_CONTRIBUTORS', 10);
define('RATE_PERIOD', 3600 * 3);
define('RATE_PERIOD_HUMAN', '3 hours');

define('GRDTOTAL_COLOR', '1435AD');
define('FBALANCE_COLOR', '4867D6');
define('CBALANCE_COLOR', 'FFB100');
define('THRESH_COLOR', 'FF0000');

if(!isset($argv[1]) || (($arg = $argv[1]) !== "update" && $arg !== "graph")) {
        die("Usage: \n".$argv[0]." update\n".$argv[0]." graph\n");
}

if(!file_exists($d = __DIR__.'/cache')) mkdir($d);

require __DIR__.'/servers.inc.php';
require __DIR__.'/sql.inc.php';

foreach($SERVERS as $shortName => $server) {
	list($fullName, $apiRoot) = $server;

	$sharesQuery = "SELECT username, COUNT(*) as s FROM shares WHERE time > ".(time() - RATE_PERIOD)." AND our_result <> 'N' AND server = '$shortName' GROUP BY username";
	$sharesQuery = mysql_query($sharesQuery);
	$shares = array();
	while($row = mysql_fetch_assoc($sharesQuery)) {
		$shares[$row['username']] = $row['s'];
	}

	$data = json_decode(file_get_contents($apiRoot.'/balances.json'));
	$currentBlock = json_decode(file_get_contents($apiRoot.'/blocks/latest.json'));
	$dataRoot = __DIR__.'/'.$shortName;
	if(!file_exists($dataRoot)) mkdir($dataRoot);
	if(!file_exists($d = $dataRoot.'/rrd')) mkdir($d);
	if(!file_exists($d = $dataRoot.'/graphs')) mkdir($d);

	$ok = array(); $inactive = array(); $malformed = array();
	foreach($data as $addr => $info) {
		if(!preg_match('%^[a-z0-9]+$%iD', $addr)) {
			$malformed[] = $addr;
			continue;
		}

		if($info->newest < time() - TIMESPAN) {
			$inactive[] = $addr;
			@unlink($dataRoot.'/rrd/'.$addr);
			@unlink($dataRoot.'/rrd/hashrate_'.$addr);
			@unlink($dataRoot.'/graphs/'.$addr);
			@unlink($dataRoot.'/graphs/hashrate_'.$addr);
			@unlink($dataRoot.'/'.$addr.'.htm');
			continue;
		}

		$total = bcmul($info->balance, "0.00000001", 8);
		$fixed = bcmul(@$currentBlock->$addr->balance ?: "0", "0.00000001", 8);
		$grandTotal = bcmul(@$info->everpaid ?: "0", "0.00000001", 8);
		if($arg === "update") {
			updateRRD($dataRoot, $fullName, $addr, $fixed, bcsub($total, $fixed, 8));
			updateRRD_grandTotal($dataRoot, $addr, $grandTotal);
			updateCache($dataRoot, $shortName, $fullName, $addr, $fixed, bcsub($total, $fixed, 8), $info, @$shares[$addr] ?: 0);
		} else if($arg === "graph") {
			updateGraph($dataRoot, $fullName, $addr);
		}

		$ok[] = $addr;
	}
	
	if($arg == "update") {
		file_put_contents(__DIR__.'/cache/'.$shortName, $ok[mt_rand(0, count($ok) - 1)].'.htm');
		updateRecentBlocks($apiRoot, $shortName);
		$hashrate = updateRRD_hashrate($dataRoot, $fullName, $shortName);
		$hashrate = number_format($hashrate / 1000000000, 2);
		$hashrate = "($hashrate Ghashes/sec)";
	} else {
		$hashrate = '';
	}

	$cOk = count($ok); $cInactive = count($inactive); $cMalformed = count($malformed);
	echo $fullName.$hashrate.": $cOk processed, $cInactive inactive, $cMalformed malformed.\n";
}

if($arg == "update") {
	updateTopContributors($SERVERS);
} else if($arg == "graph") {
	updateGraph_hashrate($SERVERS);
}


/* %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%% */

function updateGraph($dataRoot, $fullName, $addr) {
        $end = time();
        $start = $end - TIMESPAN;
        $rrd = escapeshellarg($dataRoot.'/rrd/'.$addr);
	$gTotRRD = escapeshellarg($dataRoot.'/rrd/grandtotal_'.$addr);
        $output = escapeshellarg($dataRoot.'/graphs/'.$addr);

        shell_exec($sh = 'rrdtool graph '.$output.' -s '.$start.' -e '.$end.' -v "BTC on '.$fullName.'" -t "'.$addr.'"'
		.' -w 600 -h 250 -A '
                .' DEF:f='.$rrd.':fixed:MAX '
		.' DEF:c='.$rrd.':current:MAX '
		.' DEF:gtot='.$gTotRRD.':grandtotal:MAX '
		.' CDEF:thresh=gtot,1,+ '
		.' AREA:gtot#'.GRDTOTAL_COLOR.':"Already paid":STACK '
		.' AREA:f#'.FBALANCE_COLOR.':"Unpaid reward":STACK '
		.' AREA:c#'.CBALANCE_COLOR.':"Current block reward estimate":STACK '
		.' LINE1:thresh#'.THRESH_COLOR.':"Payout threshold"'
	);
}

function updateGraph_hashrate($servers) {
        $end = time();
        $start = $end - TIMESPAN_LONG;
	$output = escapeshellarg(__DIR__.'/cache/__hashrate');

	$data = '';
	foreach($servers as $shortName => $sData) {
		list($fullName, ) = $sData;
		$rrd = __DIR__.'/'.$shortName.'/rrd/__hashrate';
		$data .= ' DEF:balance_'.$shortName.'='.$rrd.':hashrate:MAX';
		$data .= ' AREA:balance_'.$shortName.'#'.extractColor($fullName).':"'.$fullName.'":STACK';
	}

        shell_exec('rrdtool graph '.$output.' -s '.$start.' -e '.$end.' -v "Pool hash rate (Hashes/sec)"'
                .' -w 600 -h 250 -l 0 -M '.$data);
}

function updateRRD($dataRoot, $fullName, $address, $fixed, $current) {
        $rrdname = $dataRoot.'/rrd/'.$address;
        if(!file_exists($rrdname)) {
                shell_exec('rrdtool create '.escapeshellarg($rrdname).' -s '.STEP.' DS:fixed:GAUGE:'.HEARTBEAT.':0:U DS:current:GAUGE:'.HEARTBEAT.':0:U RRA:MAX:0.5:'.STEPS.':'.ROWS);
                echo "Created $rrdname.\n";
        }

        shell_exec('rrdtool update '.escapeshellarg($rrdname).' N:'.$fixed.':'.$current);
}

function updateRRD_grandTotal($dataRoot, $address, $grandTotal) {
        $rrdname = $dataRoot.'/rrd/grandtotal_'.$address;
        if(!file_exists($rrdname)) {
                shell_exec('rrdtool create '.escapeshellarg($rrdname).' -s '.STEP.' DS:grandtotal:GAUGE:'.HEARTBEAT.':0:U RRA:MAX:0.5:'.STEPS.':'.ROWS);
                echo "Created $rrdname.\n";
        }

        shell_exec('rrdtool update '.escapeshellarg($rrdname).' N:'.$grandTotal);
}

function updateRRD_hashrate($dataRoot, $longName, $shortName) {
	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_AVG_PERIOD;
	$hashrate = mysql_query("SELECT ((COUNT(*) * POW(2, 32)) / ".HASHRATE_AVG_PERIOD.") AS hashrate FROM shares WHERE our_result <> 'N' AND server = '$shortName' AND time BETWEEN $start AND $end");
	$hashrate = mysql_result($hashrate, 0, 0);
        $rrdname = $dataRoot.'/rrd/__hashrate';
        if(!file_exists($rrdname)) {
                shell_exec('rrdtool create '.escapeshellarg($rrdname).' -s '.STEP_LONG.' DS:hashrate:GAUGE:'.HEARTBEAT_LONG.':0:U RRA:MAX:0.5:'.STEPS_LONG.':'.ROWS_LONG);
                echo "Created $rrdname.\n";
        }

        shell_exec('rrdtool update '.escapeshellarg($rrdname).' N:'.$hashrate);

	return $hashrate;
}

function updateCache($dataRoot, $shortName, $fullName, $address, $fixedBalance, $currentBalance, $info, $numShares) {
	static $stats;
	if(!$stats) $stats = file_get_contents(__DIR__.'/analytics.inc.php');

	$balance = $fixedBalance + $currentBalance;
        $graphaddr = './graphs/'.$address;
	$graphaddr_hashrate = './graphs/hashrate_'.$address;
	if(!file_exists(__DIR__.'/'.$shortName.'/'.'/'.$graphaddr_hashrate)) $hashrateg = '';
	else $hashrateg = "<img src=\"$graphaddr_hashrate\" alt=\"Hashrate graph\" />";
        $mubalance = number_format($balance * 1000000, 2, '.', ','). ' μBTC';
        $muFbalance = number_format($fixedBalance * 1000000, 2, '.', ','). ' μBTC';
        $muCbalance = number_format($currentBalance * 1000000, 2, '.', ','). ' μBTC';
        $tBalance = round($balance, 2). ' BTC';
        $fBalance = round($fixedBalance, 8). ' BTC';
	$cBalance = round($currentBalance, 8). ' BTC';
        $balance = round($balance, 8).' BTC';
        $now = date('r');

	if($numShares == 0) {
		$contrib = 'This user has not submitted a share in the last '.RATE_PERIOD_HUMAN.'.';
	} else {
		$avgRate = bcdiv(bcmul(bcpow(2, 32), $numShares), RATE_PERIOD);
		$avgRate = number_format($avgRate / 1000000, 2).' Mhashes/sec';
		$numShares = number_format($numShares, 0);
		$contrib = "This user has submitted $numShares shares in the last ".RATE_PERIOD_HUMAN.". This represents a contribution in average of <strong>$avgRate</strong> to the pool.<br /><small>The rate above is just an average, and might not reflect reality, depending on luck.</small>";
	}

$html = <<<EOT
<!DOCTYPE html>
<html>
<head>
<title>($tBalance) $address on $fullName</title>
<meta charset="utf-8">
<meta http-equiv="refresh" content="300">
</head>
<body style="font-family: monospace; font-size: 0.9em;">
<h2>$address on $fullName</h2>
<p>Balance :</p>
<ul>
<li>Unpaid reward : <strong style="font-size: 1.7em;">$muFbalance</strong> <small>($fBalance)</small><br />
This is the reward you earned by contributing to the previous blocks. This amount will be paid to you when it reaches 1 BTC or after one week of inactivity, when the pool finds a block.</li>
<li>Current block estimate : <strong>$muCbalance</strong> <small>($cBalance)</small><br />
This is an estimation of the reward that you will earn when the pool finds the block it is currently working on.</li>
</ul>
<p>$contrib</p>
<p><img src="$graphaddr" alt="Balance graph" /> $hashrateg
<br />(These graphes are re-generated every 30 minutes.)</p>
<p><small><a href="../">&larr; Back to the main page</a></small><br />
<small><em>This page will automatically refresh itself in 5 minutes.<br />
This page was last updated on {$now}.</em></small></p>
$stats
</body>
</html>
EOT;
        file_put_contents($dataRoot.'/'.$address.'.htm', $html);
}

function extractColor($name) {
	$name = sha1($name);
	$r = hexdec(substr($name, 0, 2));
	$g = hexdec(substr($name, 2, 2));
	$b = hexdec(substr($name, 4, 2));

	if($r + $g + $b > 512) return extractColor($name);

	$r = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
	$g = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
	$b = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

	return $r.$g.$b;
}

function updateRecentBlocks($apiRoot, $shortName) {
	$outputFile = __DIR__.'/cache/'.$shortName.'_blocks';
	$output = "<ul>\n";
	$recent = shell_exec("ls -t $apiRoot/blocks/ | grep -E '^[0-9a-f]{64}\.json$' | head -n ".RECENT_BLOCKS);

	$recent = explode("\n", $recent);
	foreach($recent as $r) {
		if(!$r) continue;

		$when = filemtime($apiRoot.'/blocks/'.$r);
		$hash = preg_replace('%\.json$%D', '', $r);

		$currentDay = floor(time() / 86400);
		$foundDay = floor($when / 86400);
		if($currentDay == $foundDay) {
			$day = 'today';
		} else if($currentDay == $foundDay + 1) {
			$day = 'yesterday';
		} else if($currentDay - $foundDay < 7) {
			$day = date('l', $when);
		} else if($currentDay - $foundDay < 14) {
			$day = 'last '.date('l', $when);
		} else $day = date('l, F jS', $when);

		$time = 'at '.date('H:i', $when);

		$output .= "<li><a href=\"http://blockexplorer.com/block/$hash\">$hash</a>, $day $time</li>\n";
	}

	$output .= "<li><small><a href=\"http://eligius.st/~luke-jr/raw/$shortName/blocks/?C=M;O=D\">(more)</a></small></li>\n";
	
	file_put_contents($outputFile, $output."</ul>\n");
}

function updateTopContributors($SERVERS) {
	$query = "SELECT server, username, COUNT(*) AS c FROM shares WHERE time > ".(time() - RATE_PERIOD)." AND our_result <> 'N' GROUP BY username, server ORDER BY c DESC LIMIT ".TOP_CONTRIBUTORS.";";
	$result = "<ul>\n";

	$i = 0;
	$top = mysql_query($query);
	while($row = mysql_fetch_assoc($top)) {
		$addr = $row['username'];
		$server = $SERVERS[$row['server']][0];
		$shortName = $row['server'];

		$hashrate = bcdiv(bcmul(bcpow(2, 32), $row['c']), RATE_PERIOD);
		$hashrate = number_format($hashrate / 1000000, 2).' Mhashes/sec';

		$result .= "<li><a href=\"./$shortName/$addr.htm\">$addr</a> on $server with $hashrate in average</li>\n";
		$i++;
	}

	if($i == 0) {
		$result = '<p><em>This data is temporarily unavailable. Oops !</em></p>';
	}

	file_put_contents(__DIR__.'/cache/top_contrib', $result."</ul>\n");
}
