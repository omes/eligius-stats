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

array_shift($argv);

$recent = array();
$old = array();
foreach($SERVERS as $name => $d) {
	$recent[$name] = cacheFetch('blocks_recent_'.$name, $s);
	$old[$name] = cacheFetch('blocks_old_'.$name, $s);
}

foreach($argv as $hash) {
	foreach($SERVERS as $name => $d) {
		foreach($recent[$name] as &$bData) {
			if(strtolower($bData['hash']) == strtolower($hash)) {
				recountBlock($name, $bData);
			}
		}
	}
}

foreach($SERVERS as $name => $d) {
	cacheStore('blocks_recent_'.$name, $recent[$name]);
	cacheStore('blocks_old_'.$name, $old[$name]);
}

function recountBlock($server, &$bData) {
	$end = $bData['when'];
	$start = $end - $bData['duration'];

	$q = mysql_query("
		SELECT username, COUNT(*) AS fshares
		FROM shares
		WHERE our_result <> 'N'
			AND server = '$server'
			AND time BETWEEN $start AND $end
		GROUP BY username
	");

	$bData['shares_total'] = 0;
	$bData['shares'] = array();
	while($r = mysql_fetch_assoc($q)) {
		$bData['shares_total'] += $r['fshares'];
		$bData['shares'][$r['username']] = $r['fshares'];
	}
}
