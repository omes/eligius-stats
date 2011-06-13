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
 * This is a safe wrapper for json_decode.
 * @param string $file filename, or raw JSON data
 * @param bool $getFile if false, $file actually contains the raw data to be decoded.
 * @return bool|mixed|string false if an error happened, or decoded JSON data.
 */
function json_decode_safe($file, $getFile = true) {
	if($getFile) $data = file_get_contents($file);
	else $data = $file;
	$data = json_decode($data, true);
	if(($err = json_last_error()) !== JSON_ERROR_NONE) {
		trigger_error('Call to json_decode('.($getFile ? $file : '').') failed : '.$err, E_USER_WARNING);
		return false;
	}
	return $data;
}

/**
 * This is a safe wrapper for json_encode.
 * @param array $data the data to JSON-ize.
 * @param null $toFile if not-null, the JSON will be written to this file instead of being returned.
 * @return bool|string false if an error happened, or raw JSON data
 */
function json_encode_safe($data, $toFile = null) {
	$json = json_encode($data);
	if(($err = json_last_error()) !== JSON_ERROR_NONE) {
		trigger_error('Call to json_encode('.($toFile != null ? $toFile : '').') failed : '.$err, E_USER_WARNING);
		return false;
	}

	if($toFile !== null) {
		return file_put_contents($toFile, $json) !== false;
	} else return $json;
}

/**
 * Truncate a data file (ie, delete all its contents).
 * @param string $type the type of the data, one of the T_ constants.
 * @param string $identifier an unique identifier for this data (can be an address, or a pool name, …).
 * @return bool true if the operation succeeded
 */
function truncateData($type, $identifier) {
	$file = __DIR__.'/'.DATA_RELATIVE_ROOT.'/'.$type.'_'.$identifier.DATA_SUFFIX;
	if(file_exists($file)) return unlink($file);
	else return true;
}

/**
 * Append new values to a data file.
 * @param string $type the type of the data, one of the T_ constants.
 * @param string $identifier an unique identifier for this data (can be an address, or a pool name, …).
 * @param array $entries entries to add (array of array(date, value))
 * @param integer $maxTimespan defines after how many seconds the data is considered obsolete and deleted.
 * @param bool $tryRepair if true, will try to call tryRepairJson() on the file if it is corrupted.
 * @return bool true if the operation succeeded.
 */
function updateDataBulk($type, $identifier, $entries, $maxTimespan = null, $tryRepair = true) {
	if(count($entries) == 0) {
		trigger_error('No entries given.', E_USER_NOTICE);
		return false;
	}

	$file = __DIR__.'/'.DATA_RELATIVE_ROOT.'/'.$type.'_'.$identifier.DATA_SUFFIX;
	if(file_exists($file)) {
		$data = json_decode_safe($file);
		if($data === false) {
			tryRepairJson($file);
			return updateDataBulk($type, $identifier, $entries, $maxTimespan, false);
		}
	} else {
		$data = array();
	}
	$c = count($data);

	// Ensure chronological order
	usort($entries, function($a, $b) { return $a[0] - $b[0]; });

	if($c >= 1 && $data[$c - 1][0] > 1000 * $entries[0][0]) {
		if($tryRepair) {
			tryRepairJson($file);
			return updateDataBulk($type, $identifier, $entries, $maxTimespan, false);
		}
		trigger_error('New data to be inserted must be newer than the latest point.', E_USER_WARNING);
		return false;
	}
	foreach($entries as $entry) {
		$data[] = array((float)(1000 * $entry[0]), (float)($entry[1]));
	}

	// Wipe out old values from the array
	$threshold = bcmul(time() - $maxTimespan, 1000, 0);
	for($i = 0; $i < $c; ++$i) {
		if($i < ($c - 1) && $data[$i][0] < $threshold && $data[$i + 1][0] < $threshold) {
			unset($data[$i]);
			continue;
		}

		if($data[$i][0] < $threshold) {
			// We have now only one point that's too far in the past. We move him right at the boundary, to avoid
			// losing information.
			$data[$i][0] = $threshold;
		}

		break;
	}

	$data = array_values($data);
	return json_encode_safe($data, $file);
}

/**
 * Append a new value to a data file.
 * @param string $type the type of the data, one of the T_ constants.
 * @param string $identifier an unique identifier for this data (can be an address, or a pool name, …).
 * @param null|int $date if null, current date is assumed
 * @param float $value the value to insert
 * @param integer $maxTimespan defines after how many seconds the data is considered obsolete and deleted.
 * @param bool $tryRepair if true, will try to call tryRepairJson() on the file if it is corrupted.
 * @return bool true if the operation succeeded.
 */
function updateData($type, $identifier, $date = null, $value = null, $maxTimespan = null, $tryRepair = true) {
	if($date === null) $date = time();
	$data = array(array($date, $value));

	return updateDataBulk($type, $identifier, $data, $maxTimespan, $tryRepair);
}

/**
 * Try to auto-correct corrupted or malformed JSON files.
 * @param string $file which file to repair
 * @return bool true if an attempt was made to recover the JSON file.
 */
function tryRepairJson($file) {
	$contents = file_get_contents($file);
	if(strpos($contents, "]]") === false && strlen($contents) > 1) {
		$lastBracket = strrpos($contents, ',[');
		$contents = substr($contents, 0, $lastBracket).']';
		return file_put_contents($file, $contents) !== false;
	}

	if(strpos($contents, "]]]") !== false) {
		$contents = str_replace("]]]", "]]", $contents);
		return file_put_contents($file, $contents) !== false;
	}

	$data = json_decode_safe($contents, false);
	$newData = array();
	$hadError = false;
	$previousDate = -1;
	$now = time();
	foreach($data as $d) {
		if(count($d) == 2) {
			if($d[0] < $previousDate) $hadError = true;
			$previousDate = $d[0];

			if($d[0] / 1000 > $now) {
				$hadError = true;
				continue;
			}

			$newData[] = $d;
		}
		else {
			$hadError = true;
		}
	}
	if($hadError) {
		$newData = usort($newData, function($a, $b) { return $b[0] - $a[0]; });

		return json_encode_safe($newData, $file);
	}

	trigger_error("Could not repair $file.", E_USER_WARNING);
	return false;
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
function prettyDuration($duration, $align = false, $precision = 4) {
	if($duration < 60) return "a few seconds";
	else if($duration < 300) return "a few minutes";

	$units = array("month" => 30.5 * 86400, "week" => 7*86400, "day" => 86400, "hour" => 3600, "minute" => 60);

	$r = array();
	foreach($units as $u => $d) {
		$num = floor($duration / $d);
		if($num >= 1) {
			$plural = ($num > 1 ? 's' : ($align ? '&nbsp;' : ''));
			if($align && count($r) > 0) {
				$num = str_pad($num, 2, '_', STR_PAD_LEFT);
				$num = str_replace('_', '&nbsp;', $num);
			}
			$r[] = $num.' '.$u.$plural;
			$duration %= $d;
		}
	}

	$prefix = '';
	while(count($r) > $precision) {
		$prefix = 'about ';
		array_pop($r);
	}

	if(count($r) > 1) {
		$ret = array_pop($r);
		$ret = implode(', ', $r).' and '.$ret;
		return $prefix.$ret;
	} else return $prefix.$r[0];
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

/**
 * Neatly formats a (large) integer.
 * @param integer $i integer to format
 * @return string the formatted integer
 */
function prettyInt($i) {
	return number_format($i, 0, '.', ',');
}

/**
 * Get the formatted number of seconds, minutes and hours from a duration.
 * @param integer $d the duration (number of seconds)
 * @return array array($seconds, $minutes, $hours)
 */
function extractTime($d) {
	$seconds = $d % 60;
	$minutes = (($d - $seconds) / 60) % 60;
	$hours = ($d - 60 * $minutes - $seconds) / 3600;
	if($seconds) {
		$seconds .= 's';
	} else $seconds = '';
	if($minutes) {
		$minutes .= 'm';
	} else $minutes = '';
	if($hours) {
		$hours .= 'h';
	} else $hours = '';
	if($hours && $minutes == '') {
		$minutes = '0m';
	}
	if(($hours || $minutes) && $seconds == '') {
		$seconds = '0s';
	}

	return array($seconds, $minutes, $hours);
}