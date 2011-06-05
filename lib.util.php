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

const DATA_RELATIVE_ROOT = 'json';
const DATA_SUFFIX = '.json';

/**
 * Append a new value to a data file.
 * @param string $type the type of the data, one of the T_ constants.
 * @param string $identifier an unique identifier for this data (can be an address, or a pool name, …).
 * @param null|integer $date the date of the new data point. If null, current time is used.
 * @param float|string $value the value of the new data point.
 * @param integer $maxTimespan defines after how many seconds the data is considered obsolete and deleted.
 * @return bool true if the operation succeeded.
 */
function updateData($type, $identifier, $date = null, $value = null, $maxTimespan = null) {
	if($date === null) $date = microtime(true);
	if($value === null) {
		trigger_error('Null $value given.', E_USER_NOTICE);
		return false;
	}

	$date = bcmul($date, 1000, 0);

	$file = __DIR__.'/'.DATA_RELATIVE_ROOT.'/'.$type.'_'.$identifier.DATA_SUFFIX;

	if(file_exists($file)) {
		$data = json_decode(file_get_contents($file), true);
		if(($err = json_last_error()) !== JSON_ERROR_NONE) {
			trigger_error('Call to json_decode failed : '.$err, E_USER_WARNING);
			return false;
		}
	} else {
		$data = array();
	}

	$c = count($data);
	// Ensure chronological order
	if($c >= 1 && $data[$c - 1][0] > $date) {
		trigger_error('New data to be inserted must be newer than the latest point.', E_USER_WARNING);
		return false;
	}

	$data[] = array((float)$date, (float)$value);

	// Wipe out old values from the array
	$threshold = bcmul(microtime(true) - $maxTimespan, 1000, 0);
	for($i = 0; $i < $c; ++$i) {
		if($data[$i][0] < $threshold) {
			unset($data[$i]);
		} else break; // It's safe to break here, since we store the data in the chronological order.
	}

	$data = array_values($data);
	$json = json_encode($data);
	if(($err = json_last_error()) !== JSON_ERROR_NONE) {
		trigger_error('Call to json_encode failed : '.$err, E_USER_WARNING);
		return false;
	}

	return file_put_contents($file, $json) !== false;
}

/**
 * Convert a money amount from Satoshis to BTC.
 * @param string|integer $satoshi the amount in Satoshis
 * @return string the specified amount, in BTC.
 */
function satoshiToBTC($satoshi) {
	return bcmul($satoshi, "0.00000001", 8);
}

/**
 * Format a duration in a human-readable way.
 * @param int|float $duration the time, in seconds, to format
 * @param bool $align whether we should align the components.
 * @return string a human-readable version of the same duration
 */
function prettyDuration($duration, $align = false) {
	if($duration < 60) return "a few seconds";
	else if($duration < 300) return "a few minutes";

	$units = array("week" => 7*86400, "day" => 86400, "hour" => 3600, "minute" => 60);

	$r = array();
	foreach($units as $u => $d) {
		$num = floor($duration / $d);
		if($num >= 1) {
			$plural = ($num > 1 ? 's' : '');
			if($align && count($r) > 0) {
				$num = str_pad($num, 2, '_', STR_PAD_LEFT);
				$num = str_replace('_', '&nbsp;', $num);
			}
			$r[] = $num.' '.$u.$plural;
			$duration %= $d;
		}
	}

	if(count($r) > 1) {
		$ret = array_pop($r);
		$ret = implode(', ', $r).' and '.$ret;
		return $ret;
	} else return $r[0];
}

/**
 * Format a hashrate in a human-readable fashion.
 * @param int|float|string $hps the number of hashes per second
 * @return string a formatted rate
 */
function prettyHashrate($hps) {
	if($hps < 10000000) {
		return number_format($hps / 1000, 2).' Khashes/sec';
	} else if($hps < 10000000000) {
		return number_format($hps / 1000000, 2).' Mhashes/sec';
	} else return number_format($hps / 1000000000, 2).' Ghashes/sec';
}

/**
 * Extract a not-too-dark, not-too-light color from anything.
 * @param mixed $seed the seed to extract the color from.
 * @return string a color in the rgb($r, $g, $b) format.
 */
function extractColor($seed) {
	static $threshold = 100;

	$d = sha1($seed);

	$r = hexdec(substr($d, 0, 2));
	$g = hexdec(substr($d, 2, 2));
	$b = hexdec(substr($d, 4, 2));

	if($r + $g + $b < $threshold || $r + $g + $b > (3*255 - $threshold)) return extractColor($d);
	else return "rgb($r, $g, $b)";
}