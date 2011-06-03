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
define('STEPS', 3);
define('ROWS', 672);

define('TIMESPAN_LONG', 31 * 86400);
define('STEPS_LONG', STEPS);
define('ROWS_LONG', 2976); /* ROWS * (31 / 7) */

define('RECENT_BLOCKS', 7);

$now = time();
$start = $now - 32 * 86400;
$interval = 3600;
$step = 300;
$servers = array('us', 'eu');

mysql_connect('127.0.0.1', 'artefact2', 'PyNcjfnCeB2ADFnK');
mysql_select_db('eligius');
bcscale(2);

foreach($servers as $shortName) {
	$current = $start;
	while($current < $now) {
		$upperBound = $current + $interval;
		$shares = mysql_query("SELECT COUNT(*) FROM shares WHERE shares.time BETWEEN $current AND $upperBound AND server = '$shortName' AND our_result <> 'N'");
		$shares = mysql_result($shares, 0, 0);
		$hashrate = bcdiv(bcmul(bcpow(2, 32), $shares), $interval);
		
		$command = '';
		while($current < $upperBound) {
			$command .= ' '.$current.':'.$hashrate;
			$current += $step;
		}

		$command = 'rrdtool update hashrate_'.$shortName.$command;
		shell_exec($command);
		var_dump($now - $current);
	}
}

echo "\n";
