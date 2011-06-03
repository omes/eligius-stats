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

require __DIR__.'/servers.inc.php';

$output = "<table>\n<tr><th>Status</th><th>Host</th><th>Latency</th><th>Uptime <small>(<a href=\"api/events.json\">API</a>)</small></th></tr>\n";

$statusesF = __DIR__.'/cache/pool_statuses';
$statuses = unserialize(@file_get_contents($statusesF));

foreach($SERVERS_ADDRESSES as $s => $realURI) {
	$status = 'Unknown';
	$color = 'yellow';

	list($server, $port) = explode(':', $s);
	$error = ''; $latency = 0;
	if(getwork($server, $port, $error, $latency)) {
		$status = 'Giving work, OK'; $color = 'greenyellow';
		$mStatus = 'up';
		$prefix = 'Uptime : ';
		if($latency < 1) $lag = '<1 s';
		else $lag = number_format($latency, 2).' s';
	} else {
		$status = $error; $color = 'red';
		$mStatus = 'down';
		$prefix = 'Downtime : ';
		$lag = 'N/A';
	}

	if(!isset($statuses[$realURI]['stat'])) {
		$statuses[$realURI]['stat'] = 'unknown';
	}
	if($statuses[$realURI]['stat'] != $mStatus) {
		handleStatusChange($realURI, $statuses[$realURI]['stat'], $mStatus);
		$statuses[$realURI]['last'] = time();
		$statuses[$realURI]['stat'] = $mStatus;
	}

	$uptime = $prefix.prettyDuration(time() - $statuses[$realURI]['last']);

	$output .= "<tr><td style=\"background-color: $color; padding: 0.5em;\"><strong>$status</strong></td>\n<td style=\"padding: 0.5em;\">$realURI</td><td style=\"padding: 0.5em;\">$lag</td><td>$uptime</td></tr>\n";
}

$output .= "</table>\n";

file_put_contents(__DIR__.'/cache/status', $output);
file_put_contents($statusesF, serialize($statuses));

function handleStatusChange($server, $oldStatus, $newStatus) {
	if(!file_exists($s = __DIR__.'/api')) mkdir($s);
	if(file_exists($f = __DIR__.'/api/events.json')) {
		$json = json_decode(file_get_contents($f), true); // Return associative array
	} else $json = array();

	$json[] = array(
		"date" => time(),
		"host" => $server,
		"from" => $oldStatus,
		"to" => $newStatus 
	);

	while(count($json) > 25) array_shift($json);

	file_put_contents($f, json_encode($json)."\n");
}

function prettyDuration($duration) {
	$units = array("week" => 7*86400, "day" => 86400, "hour" => 3600, "minute" => 60);

	$r = array();
	foreach($units as $u => $d) {
		$num = floor($duration / $d);
		if($num >= 1) {
			$r[] = $num.' '.$u.($num > 1 ? 's' : '');
			$duration %= $d;
		}
	}

	if(count($r) > 1) {
		$ret = array_pop($r);
		$ret = implode(', ', $r).' and '.$ret;
		return $ret;
	} else return $r[0];
}

function getwork($server, $port, &$error, &$latency) {
	$body = json_encode(array(
                "method" => "getwork",
                "params" => array(),
                "id" => 42
        ));

	$c = curl_init();
	curl_setopt($c, CURLOPT_POST, true);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_HEADER, true);
	
	curl_setopt($c, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json'
	));
	curl_setopt($c, CURLOPT_POSTFIELDS, $body);
	curl_setopt($c, CURLOPT_URL, 'http://artefact2:test@'.$server.':'.$port.'/');
	
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_TIMEOUT, 30);

	$lag = microtime(true);
	$resp = curl_exec($c);
	$latency = microtime(true) - $lag;

	if($e = curl_error($c))  {
		$error = 'Server unreachable / Timeout / Network problem';
		return false;
	}
	curl_close($c);

	if(strpos($resp, 'HTTP/1.1 200') !== 0) {
		$error = 'Invalid HTTP header';
		return false;
	}

	if(strpos($resp, 'Content-Type: application/json') === false) {
		$error = 'No JSON reply';
		return false;
	}

	$work = json_decode(substr($resp, strpos($resp, '{') - 1));
	if(@strlen($work->result->data) !== 256 || @$work->error !== null) {
		$error = 'Invalid work given';
		return false;
	}

	return true;
}
