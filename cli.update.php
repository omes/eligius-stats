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

function showUsage($commands) {
	echo 'Usage : '.$_SERVER['argv'][0].' <command> [<command2> [<command3> […]]]'."\n";
	echo 'Available commands :'."\n";
	foreach($commands as $c => $callback) {
		echo '	'.$c."\n";
	}
}

$commands = array(
	'balances' => function() use($SERVERS) {
		$r = true;
		foreach($SERVERS as $name => $data) {
			list(, $apiRoot) = $data;
			$r = $r && updateAllBalancesOnServer($name, $apiRoot);
		}
		return $r;
	},

	'individual_hashrates' => function() use($SERVERS) {
		$r = true;
		foreach($SERVERS as $name => $data) {
			list(, $apiRoot) = $data;
			$r = $r && updateAllIndividualHashratesOnServer($name, $apiRoot);
		}
		return $r;
	},

	'pool_hashrates' => function() use($SERVERS) {
		$r = true;
		foreach($SERVERS as $name => $data) {
			$r = $r && updatePoolHashrate($name);
		}
		return $r;
	},

	'pool_status' => function() use($SERVERS_ADDRESSES) {
		$r = true;
		foreach($SERVERS_ADDRESSES as $serverName => $data) {
			list($realAddress,) = $data;
			list($address, $port) = explode(':', $realAddress);
			$r = $r && updateServerStatus($serverName, $address, $port);
		}
		return $r;
	},

	'recent_blocks' => function() use($SERVERS) {
		$r = true;
		foreach($SERVERS as $serverName => $data) {
			list(, $apiRoot) = $data;
			$r = $r && updateRecentBlocks($serverName, $apiRoot);
		}
		return $r;
	},

	'top_contributors' => function() {
		return updateTopContributors();
	},
);

if($_SERVER['argc'] == 1 || in_array($_SERVER['argv'][1], array('-h', '--help', 'help', '/?', '/help', '--?', '-?'))) {
	showUsage($commands);
	die(42);
}

array_shift($_SERVER['argv']);

foreach($_SERVER['argv'] as $cmd) {
	if(!isset($commands[$cmd])) {
		showUsage($commands);
		echo "\nUnknown command : $cmd\n";
		die(42);
	}
}

$r = true;
foreach($_SERVER['argv'] as $cmd) {
	$r = $r && call_user_func($commands[$cmd]);
}
die($r === true ? 0 : 1);