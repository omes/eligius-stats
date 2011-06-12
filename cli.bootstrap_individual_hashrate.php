#!/usr/bin/env php
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

const INTERVAL = HASHRATE_PERIOD;

foreach($SERVERS as $name => $data) {
	$now = time();
	$current = $now - TIMESPAN_SHORT + 42;

	list(, $apiRoot) = $data;
	$addresses = getActiveAddresses($apiRoot);

	$rates = array();

	while($current < $now - INTERVAL) {
		$start = $current;
		$end = $current + INTERVAL;
		$hashrates = mysql_query($q = "
			SELECT username AS address, ((COUNT(*) * POW(2, 32)) / ".INTERVAL.") AS hashrate
			FROM shares
			WHERE our_result <> 'N'
				AND server = '$name'
				AND time BETWEEN $start AND $end
			GROUP BY address
		");

		$row = array();
		while($r = mysql_fetch_assoc($hashrates)) {
			$hashrate = $r['hashrate'];
			$address = $r['address'];

			$row[$address] = $hashrate;
		}
		
		foreach($addresses as $address) {
			$hashrate = isset($row[$address]) ? $row[$address] : 0;
			$rates[$address][] = array($current, $hashrate);
		}

		$current += INTERVAL;
		echo '.';
	}
	
	foreach($rates as $address => $entries) {
		truncateData(T_HASHRATE_INDIVIDUAL, $F = $name.'_'.$address);
		updateDataBulk(T_HASHRATE_INDIVIDUAL, $F, $entries, TIMESPAN_SHORT);
	}

	echo "\n";
}