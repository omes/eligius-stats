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

// THIS SCRIPT IS A ONE-TIME USE ONLY ! USE WITH CAUTION.

die(42);

define('TIMESPAN', 7 * 86400);
define('STEP', 300);
define('HEARTBEAT', 600);
define('STEPS', 3);
define('ROWS', 672);

$now = time();
$start = $now - TIMESPAN - 86400;
$servers = array('us', 'eu');

bcscale(2);

foreach($servers as $shortName) {
	$current = 1 + $start;

	$bApi = json_decode(file_get_contents('/var/lib/eligius/'.$shortName.'/balances.json'));
	$addresses = array();
	foreach($bApi as $addr => $crap) $addresses[] = $addr;
	/* Ugly hack, remove the last empty entry */ array_pop($addresses);

	$dates = glob('/var/lib/eligius/'.$shortName.'/blocks/0000*.json');
	$dates = array_flip($dates); 
	foreach($dates as $f => &$v) $v = filemtime($f);
	asort($dates);

	$totals = array();
	foreach($dates as $f => $d) {
		$json = json_decode(file_get_contents($f), true);
		array_pop($json);
		foreach($json as $addr => $data) {
			$tot = @$data['everpaid'] ?: 0;
			$tot = bcmul($tot, "0.00000001", 8);
			$totals[$f][$addr] = $tot;
		}
	}

	$totals['__NOW__'] = $totals[$f];
	$dates['__NOW__'] = time() + STEP - 1;

	$rrds = array();
	foreach($addresses as $addr) {
		$rrd = __DIR__.'/'.$shortName.'/rrd/grandtotal_'.$addr;
		if(file_exists($rrd)) die("Some RRD files already exist.\n");
		shell_exec('rrdtool create '.escapeshellarg($rrd).' -b '.$start.' -s '.STEP.' DS:grandtotal:GAUGE:'.HEARTBEAT.':0:U RRA:MAX:0.5:'.STEPS.':'.ROWS);
		$rrds[$addr] = $rrd;
	}

	$commands = array();

	$previous = @array_pop(array_keys($totals));	

	while($current < $now) {
		foreach($dates as $f => $d) {
			if($d <= $current) {
				continue;
			}

			$stopAt = $d;
			if(isset($currentBlock)) $previous = $currentBlock;
			$currentBlock = $f;
			echo "Processing $currentBlock...\n";

			break;
		}

		while($current < $stopAt) {
			foreach($rrds as $addr => $rrd) {
				$total = @$totals[$previous][$addr] ?: 0;
				$total = bcadd($total, '0', 2);
				@$commands[$addr] .= ' '.$current.':'.$total;
			}
			$current += STEP;
		}
	}

	echo "Filling RRDs.\n";

	foreach($commands as $addr => $cmd) {
		shell_exec('rrdtool update '.escapeshellarg($rrds[$addr]).$cmd);
		echo ".";
	}

	echo "\n";
}

