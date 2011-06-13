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

define('NO_SQL', true);
require __DIR__.'/lib.eligius.php';
require __DIR__.'/inc.servers.php';

const NUM_CONFIRMATIONS = 120; /* Number of confirmations before a block is considered "valid" */

if(strpos(shell_exec('bitcoind getblockbycount 42'), '00000000314e90489514c787d615cea50003af2023796ccdd085b6bcc1fa28f5') === false) {
	die("No suitable bitcoind in the PATH.\n");
}

$lackOfConfirmations = array();
$blockCount = shell_exec('bitcoind getblockcount');
for($i = (NUM_CONFIRMATIONS - 1); $i >= 0; --$i) {
	$n = $blockCount - $i;
	$bData = json_decode(shell_exec('bitcoind getblockbycount '.$n), true);
	$lackOfConfirmations[$bData['hash']] = NUM_CONFIRMATIONS - $i;
}

foreach($SERVERS as $name => $d) {
	$old = cacheFetch('blocks_old_'.$name, $s0);
	$recent = cacheFetch('blocks_recent_'.$name, $s1);

	if(!$s0 || !$s1) {
		trigger_error('Cannot fetch block metadata for '.$name.' : could not fetch cached blocks.', E_USER_WARNING);
		continue;
	}

	$c = count($recent);
	$d = count($old);

	// Update blocks in the chronological order (oldest blocks first)

	for($i = ($d - 1); $i >= 0; --$i) {
		if($i < ($d - 1)) {
			$previous = $old[$i + 1];
		} else $previous = null;

		updateBlock($old[$i], $previous);
	}

	for($i = ($c - 1); $i >= 0; --$i) {
		if($i < ($c - 1)) {
			$previous = $recent[$i + 1];
		} else if($d > 0) {
			$previous = $old[0];
		} else $previous = null;

		updateBlock($recent[$i], $previous);
	}

	$s0 = cacheStore('blocks_old_'.$name, $old);
	$s1 = cacheStore('blocks_recent_'.$name, $recent);

	if(!$s0 || !$s1) {
		trigger_error('Cannot store block metadata for '.$name.' : could not store cached blocks.', E_USER_WARNING);
		continue;
	}
}

function updateBlock(&$block, $previousBlock = null) {
	global $lackOfConfirmations;

	if(isset($block['has_metadata']) && $block['has_metadata']) return;

	$json = shell_exec('bitcoind getblockbyhash '.$block['hash'].' 2>&1');
	if(strpos($json, 'error:') === 0) {
		$block['valid'] = false;
		return;
	} else if(isset($lackOfConfirmations[$block['hash']])) {
		$block['valid'] = null;
	} else {
		$block['valid'] = true;
		$block['has_metadata'] = true;
	}

	$bData = json_decode($json, true);
	$block['when'] = $bData['time'];
	if($previousBlock == null) {
		unset($block['duration']);
	} else {
		$block['duration'] = $block['when'] - $previousBlock['when'];
	}
}