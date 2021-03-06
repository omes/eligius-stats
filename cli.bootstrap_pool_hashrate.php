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

const INTERVAL = HASHRATE_PERIOD_LONG;

foreach($SERVERS as $name => $data) {
	$now = time();
	$current = $now - TIMESPAN_LONG + 42;

	$rates = array();
	while($current < $now - INTERVAL) {
		$start = $current;
		$end = $current + INTERVAL;
		$hashrate = mysql_query($q = "
			SELECT ((COUNT(*) * POW(2, 32)) / ".INTERVAL.") AS hashrate
			FROM shares
			WHERE our_result <> 'N'
				AND server = '$name'
				AND time BETWEEN $start AND $end
		");

		$hashrate = mysql_fetch_assoc($hashrate);
		$hashrate = $hashrate['hashrate'];

		$rates[] = array($current, $hashrate);

		$current += INTERVAL;
		echo '.';
	}

	truncateData(T_HASHRATE_POOL, $name);
	updateDataBulk(T_HASHRATE_POOL, $name, $rates, TIMESPAN_LONG);

	echo "\n";
}