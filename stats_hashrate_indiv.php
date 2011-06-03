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

define('TIMESPAN', 4 * 86400);
define('STEP', 180);
define('HEARTBEAT', 360);
define('STEPS', 3);
define('ROWS', 640);

assert('STEP * STEPS * ROWS == TIMESPAN');

define('HASHRATE_AVG_PERIOD', 180);
define('HASHRATE_LAG', 300);

if(!isset($argv[1]) || (($arg = $argv[1]) !== "update" && $arg !== "graph")) {
        die("Usage: \n".$argv[0]." update\n".$argv[0]." graph\n");
}

require __DIR__.'/servers.inc.php';
require __DIR__.'/sql.inc.php';

bcscale(2);

foreach($SERVERS as $shortName => $server) {
	list($fullName, $apiRoot) = $server;
	$dataRoot = __DIR__.'/'.$shortName;

	$end = time() - HASHRATE_LAG;
	$start = $end - HASHRATE_AVG_PERIOD;
	$sharesQuery = "SELECT username, COUNT(*) as s FROM shares WHERE time BETWEEN $start AND $end AND our_result <> 'N' AND server = '$shortName' GROUP BY username";
	$sharesQuery = mysql_query($sharesQuery);
	$rates = array();
	$pow = bcpow(2, 32);
	while($row = mysql_fetch_assoc($sharesQuery)) {
		$rates[$row['username']] = bcdiv(bcmul($pow, $row['s']), HASHRATE_AVG_PERIOD);
	}

	if(!file_exists($dataRoot)) mkdir($dataRoot);
	if(!file_exists($d = $dataRoot.'/rrd')) mkdir($d);
	if(!file_exists($d = $dataRoot.'/graphs')) mkdir($d);

	$ok = array(); $inactive = array(); $malformed = array();
	foreach($rates as $addr => $hashrate) {
		if(!preg_match('%^[a-z0-9]+$%iD', $addr)) {
			$malformed[] = $addr;
			continue;
		}

		if($arg === "update") {
			updateRRD_hashrate_indiv($dataRoot, $fullName, $addr, $hashrate);
		} else if($arg === "graph") {
			updateGraph_hashrate_indiv($dataRoot, $fullName, $addr);
		}

		$ok[] = $addr;
	}
	
	$cOk = count($ok); $cInactive = count($inactive); $cMalformed = count($malformed);
	echo $fullName.": $cOk processed, $cInactive inactive, $cMalformed malformed.\n";
}

/* %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%% */

function updateGraph_hashrate_indiv($dataRoot, $fullName, $addr) {
        $end = time();
        $start = $end - TIMESPAN;
        $rrd = escapeshellarg($rrdFName = $dataRoot.'/rrd/hashrate_'.$addr);
        $output = escapeshellarg($dataRoot.'/graphs/hashrate_'.$addr);

	if(!file_exists($rrdFName)) return;

        shell_exec('rrdtool graph '.$output.' -s '.$start.' -e '.$end.' -v "Hashes/sec on '.$fullName.'" -t "'.$addr.'"'
		.' -w 600 -h 250 '
                .' DEF:h='.$rrd.':hashrate:AVERAGE AREA:h#70DB93:"Measured hashrate"'
		.' CDEF:shortavg=h,1800,TREND LINE1:shortavg#213D30:"30-min average"'
		.' CDEF:longavg=h,7200,TREND LINE2:longavg#660000:"2-hour average"'
		);
}

function updateRRD_hashrate_indiv($dataRoot, $fullName, $address, $hashrate) {
        $rrdname = $dataRoot.'/rrd/hashrate_'.$address;
        if(!file_exists($rrdname)) {
                shell_exec('rrdtool create '.escapeshellarg($rrdname).' -s '.STEP.' DS:hashrate:GAUGE:'.HEARTBEAT.':0:U RRA:AVERAGE:0.5:'.STEPS.':'.ROWS);
                echo "Created $rrdname.\n";
        }

        shell_exec('rrdtool update '.escapeshellarg($rrdname).' N:'.$hashrate);
}

