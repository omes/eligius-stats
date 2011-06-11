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

foreach($SERVERS as $name => $data) {
	list(,$apiRoot) = $data;

	$toCommit = array();

	$gBlocks = glob($apiRoot.'/blocks/0000*.json');
	$blocks = array();
	foreach($gBlocks as $block) {
		$blocks[$block] = filemtime($block);
	}
	asort($blocks);

	foreach($blocks as $block => $foundAt) {
		$blk = file_get_contents($block);
		$blk = json_decode($blk, true);

		foreach($blk as $address => $addressData) {
			$toCommit[T_BALANCE_UNPAID_REWARD][$address][] = array($foundAt, (isset($addressData['balance']) ? satoshiToBTC($addressData['balance']) : "0.0"));
			$toCommit[T_BALANCE_ALREADY_PAID][$address][] = array($foundAt, (isset($addressData['everpaid']) ? satoshiToBTC($addressData['everpaid']) : "0.0"));
		}

		echo '.';
	}

	foreach($toCommit as $type => $kzk) {
		foreach($kzk as $address => $entries) {
			truncateData($type, $F = $name.'_'.$address);
			updateDataBulk($type, $F, $entries, TIMESPAN_SHORT);
		}
	}

	echo "\n";
}