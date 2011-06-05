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
			if(isset($addressData['balance'])) {
				updateData(T_BALANCE_UNPAID_REWARD, $name.'_'.$address, $foundAt, satoshiToBTC($addressData['balance']), TIMESPAN_SHORT);
			}
			if(isset($addressData['everpaid'])) {
				updateData(T_BALANCE_ALREADY_PAID, $name.'_'.$address, $foundAt, satoshiToBTC($addressData['everpaid']), TIMESPAN_SHORT);
			}
		}

		echo '.';
	}

	echo "\n";
}