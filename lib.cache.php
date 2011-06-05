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

const FILE_CACHE_DIR = 'cache';
const C_PLAIN_FILES = 0;
/** @todo memcached support */


/**
 * Get the best cache type available on this machine.
 * @return int the best available cache.
 */
function getBestCache() {
	static $result = null;
	if($result !== null) return $result;

	$result = C_PLAIN_FILES;

	return $result;
}

/**
 * Store a value in the cache.
 * @param string $key the identifier of the data.
 * @param mixed $value the data to store in the cache.
 * @return bool whether the operation succeeded.
 */
function cacheStore($key, $value) {
	$c = getBestCache();
	if($c == C_PLAIN_FILES) {
		return file_put_contents(__DIR__.'/'.FILE_CACHE_DIR.'/'.$key.'.cache', serialize($value)) !== false;
	} else {
		return false;
	}
}

/**
 * Retrieve a value from the cache.
 * @param string $key the identifier of the data.
 * @param bool $success passed by reference, is set to true if the data was correctly fetched.
 * @return mixed the data stored in the cache, if any.
 */
function cacheFetch($key, &$success) {
	$c = getBestCache();
	if($c == C_PLAIN_FILES) {
		if(!file_exists($f = __DIR__.'/'.FILE_CACHE_DIR.'/'.$key.'.cache')) {
			$success = false;
			return null;
		}

		$data = file_get_contents($f);
		$success = $data !== false;
		if($success) return unserialize($data);
		else return null;
	} else {
		$success = false;
		return null;
	}
}